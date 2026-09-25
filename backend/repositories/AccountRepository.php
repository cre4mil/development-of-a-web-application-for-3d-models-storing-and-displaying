<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Users, creator wallets, payout requests and platform settings. */
final class AccountRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, username, password, is_admin FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $this->pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)')->execute([$username, $email, $passwordHash]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, username, email, is_admin FROM users WHERE id = ?');
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT id, username, email, is_admin FROM users ORDER BY id DESC')->fetchAll();
    }

    /** True when deleting the user would erase money-related records (sales, purchases or payouts). */
    public function hasFinancialHistory(int $userId): bool
    {
        foreach ([
            'SELECT 1 FROM order_items WHERE creator_id = ? LIMIT 1',
            'SELECT 1 FROM orders WHERE buyer_id = ? LIMIT 1',
            'SELECT 1 FROM payout_requests WHERE creator_id = ? LIMIT 1',
        ] as $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([$userId]);
            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function modelsOf(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT id, filename, file_gltf, file_glb, file_usdz, file_obj, thumb FROM models WHERE user_id = ?');
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** Deletes a user together with their own interactions; their models must already be removed. */
    public function delete(int $userId): void
    {
        foreach ([
            'DELETE FROM likes WHERE user_id = ?',
            'DELETE FROM comments WHERE user_id = ?',
            'DELETE FROM collections WHERE user_id = ?',
            'DELETE FROM follows WHERE follower_id = ? OR followee_id = ?',
            'DELETE FROM creator_wallets WHERE creator_id = ?',
            'DELETE FROM users WHERE id = ?',
        ] as $sql) {
            $this->pdo->prepare($sql)->execute(array_fill(0, substr_count($sql, '?'), $userId));
        }
    }

    /** @return array<string, mixed> */
    public function wallet(int $creatorId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM creator_wallets WHERE creator_id = ?');
        $statement->execute([$creatorId]);
        $wallet = $statement->fetch();

        return $wallet === false ? [
            'creator_id' => $creatorId,
            'available_balance' => 0,
            'total_earned' => 0,
            'pending_payout' => 0,
            'bank_name' => '',
            'bank_account_no' => '',
            'bank_account_name' => '',
        ] : $wallet;
    }

    public function saveBank(int $creatorId, string $bankName, string $accountNo, string $accountName): void
    {
        $update = $this->pdo->prepare('UPDATE creator_wallets SET bank_name = ?, bank_account_no = ?, bank_account_name = ? WHERE creator_id = ?');
        $update->execute([$bankName, $accountNo, $accountName, $creatorId]);
        if ($update->rowCount() === 0) {
            $this->pdo->prepare('INSERT INTO creator_wallets (creator_id, bank_name, bank_account_no, bank_account_name) VALUES (?, ?, ?, ?)')
                ->execute([$creatorId, $bankName, $accountNo, $accountName]);
        }
    }

    public function feePercent(float $default): float
    {
        $value = $this->pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_pct'")->fetchColumn();

        return $value === false ? $default : (float) $value;
    }

    public function saveFee(float $percent, int $adminId): void
    {
        $update = $this->pdo->prepare("UPDATE platform_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'platform_fee_pct'");
        $update->execute([(string) $percent, $adminId]);
        if ($update->rowCount() === 0) {
            $this->pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value, updated_by) VALUES ('platform_fee_pct', ?, ?)")
                ->execute([(string) $percent, $adminId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function wallets(): array
    {
        return $this->pdo->query('SELECT w.*, u.username, u.email FROM creator_wallets w JOIN users u ON u.id = w.creator_id ORDER BY w.available_balance DESC, w.creator_id ASC')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function payouts(int $limit): array
    {
        return $this->pdo->query('SELECT p.*, u.username AS creator_name, u.email AS creator_email
            FROM payout_requests p JOIN users u ON u.id = p.creator_id ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int) $limit)->fetchAll();
    }

    /** @return array{pending: int, paid: float, ready: int} */
    public function payoutStats(float $threshold): array
    {
        $ready = $this->pdo->prepare('SELECT COUNT(*) FROM creator_wallets WHERE available_balance >= ?');
        $ready->execute([$threshold]);

        return [
            'pending' => (int) $this->pdo->query("SELECT COUNT(*) FROM payout_requests WHERE status = 'pending'")->fetchColumn(),
            'paid' => (float) $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payout_requests WHERE status = 'transferred'")->fetchColumn(),
            'ready' => (int) $ready->fetchColumn(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function payout(int $payoutId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $statement->execute([$payoutId]);
        $payout = $statement->fetch();

        return $payout === false ? null : $payout;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
