<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class PdoOrderRepository implements OrderStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function purchasableModels(array $modelIds): array
    {
        $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
        $statement = $this->pdo->prepare("SELECT id, user_id, price FROM models WHERE id IN ($placeholders) AND is_public = 1 AND price > 0");
        $statement->execute($modelIds);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasActiveOrder(int $buyerId, int $modelId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status IN ('approved', 'pending') LIMIT 1"
        );
        $statement->execute([$buyerId, $modelId]);

        return $statement->fetchColumn() !== false;
    }

    public function platformFeePercent(float $default): float
    {
        $fee = $this->pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_pct'")->fetchColumn();
        return $fee === false ? $default : (float) $fee;
    }

    public function createOrder(string $reference, int $buyerId, float $total, string $slipUrl, string $status, ?string $note, array $items): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO orders (order_ref, buyer_id, total_amount, payment_slip_url, payment_status, admin_note, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$reference, $buyerId, $total, $slipUrl, $status, $note, $status === 'approved' ? date('Y-m-d H:i:s') : null]);
        $orderId = (int) $this->pdo->lastInsertId();
        $itemStatement = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, model_id, creator_id, price, platform_fee, creator_earning) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStatement->execute([$orderId, $item['model_id'], $item['creator_id'], $item['price'], $item['platform_fee'], $item['creator_earning']]);
        }

        return $orderId;
    }

    public function findOrder(int $orderId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, payment_status FROM orders WHERE id = ?');
        $statement->execute([$orderId]);
        $order = $statement->fetch(PDO::FETCH_ASSOC);

        return $order === false ? null : $order;
    }

    public function isAdmin(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
        $statement->execute([$userId]);
        return (bool) $statement->fetchColumn();
    }

    public function updateStatus(int $orderId, string $status, ?string $note, int $adminId): void
    {
        $statement = $this->pdo->prepare('UPDATE orders SET payment_status = ?, admin_note = ?, admin_approved_by = ?, approved_at = ? WHERE id = ?');
        $statement->execute([$status, $note, $adminId, $status === 'approved' ? date('Y-m-d H:i:s') : null, $orderId]);
    }

    public function earningsForOrder(int $orderId): array
    {
        $statement = $this->pdo->prepare('SELECT creator_id, SUM(creator_earning) AS earn FROM order_items WHERE order_id = ? GROUP BY creator_id');
        $statement->execute([$orderId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creditWallet(int $creatorId, float $amount): void
    {
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO creator_wallets (creator_id, available_balance, total_earned) VALUES (?, ?, ?) ON CONFLICT(creator_id) DO UPDATE SET available_balance = available_balance + excluded.available_balance, total_earned = total_earned + excluded.total_earned'
            : 'INSERT INTO creator_wallets (creator_id, available_balance, total_earned) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE available_balance = available_balance + VALUES(available_balance), total_earned = total_earned + VALUES(total_earned)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$creatorId, $amount, $amount]);
    }

    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
