<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Read-side queries for orders, sales and earnings. */
final class CommerceQueries
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Latest relevant order state of a buyer for a model: 'approved', 'pending' or null. */
    public function purchaseState(int $buyerId, int $modelId): ?string
    {
        $statement = $this->pdo->prepare('SELECT o.payment_status FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE o.buyer_id = ? AND oi.model_id = ?');
        $statement->execute([$buyerId, $modelId]);
        $states = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('approved', $states, true)) {
            return 'approved';
        }

        return in_array('pending', $states, true) ? 'pending' : null;
    }

    /** @return list<array<string, mixed>> orders placed by a buyer, each with its items */
    public function buyerOrders(int $buyerId): array
    {
        $statement = $this->pdo->prepare('SELECT id, order_ref, total_amount, payment_status, admin_note, created_at FROM orders WHERE buyer_id = ? ORDER BY created_at DESC, id DESC');
        $statement->execute([$buyerId]);

        return $this->withItems($statement->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public function adminOrders(string $status): array
    {
        $sql = 'SELECT o.id, o.order_ref, o.total_amount, o.payment_status, o.payment_slip_url, o.admin_note, o.created_at,
                b.username AS buyer_name
            FROM orders o JOIN users b ON b.id = o.buyer_id';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' WHERE o.payment_status = ?';
            $params[] = $status;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY o.created_at DESC, o.id DESC');
        $statement->execute($params);

        return $this->withItems($statement->fetchAll());
    }

    /** @return array{pending: int, approved: int, rejected: int} */
    public function orderCounts(): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($this->pdo->query('SELECT payment_status, COUNT(*) AS total FROM orders GROUP BY payment_status')->fetchAll() as $row) {
            $counts[$row['payment_status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function revenue(): float
    {
        return (float) $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'approved'")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function dashboardOrders(): array
    {
        return $this->pdo->query('SELECT o.id, o.order_ref, o.total_amount, o.payment_slip_url, o.payment_status, o.created_at, b.username AS buyer,
                (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count,
                (SELECT m.title FROM order_items i JOIN models m ON m.id = i.model_id WHERE i.order_id = o.id ORDER BY i.id ASC LIMIT 1) AS model_title
            FROM orders o JOIN users b ON b.id = o.buyer_id ORDER BY o.created_at DESC, o.id DESC')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function sales(int $creatorId, int $limit): array
    {
        $statement = $this->pdo->prepare('SELECT oi.model_id, oi.price, oi.platform_fee, oi.creator_earning, oi.created_at,
                m.title AS model_title, m.thumb AS model_thumb, u.username AS buyer_name, o.order_ref, o.payment_status
            FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN models m ON m.id = oi.model_id JOIN users u ON u.id = o.buyer_id
            WHERE oi.creator_id = ? ORDER BY oi.created_at DESC, oi.id DESC LIMIT ' . (int) $limit);
        $statement->execute([$creatorId]);

        return $statement->fetchAll();
    }

    /** @return list<array{month: string, earning: float, sales: int}> approved earnings for the last $months months */
    public function monthlyEarnings(int $creatorId, int $months): array
    {
        $statement = $this->pdo->prepare("SELECT oi.created_at, oi.creator_earning FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE oi.creator_id = ? AND o.payment_status = 'approved' AND oi.created_at >= ? ORDER BY oi.created_at ASC");
        $statement->execute([$creatorId, date('Y-m-d H:i:s', strtotime("-{$months} months"))]);
        $byMonth = [];
        foreach ($statement->fetchAll() as $row) {
            $month = date('Y-m', (int) strtotime((string) $row['created_at']));
            $byMonth[$month] ??= ['month' => $month, 'earning' => 0.0, 'sales' => 0];
            $byMonth[$month]['earning'] += (float) $row['creator_earning'];
            $byMonth[$month]['sales']++;
        }
        ksort($byMonth);

        return array_values($byMonth);
    }

    public function soldModelCount(int $creatorId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(DISTINCT model_id) FROM order_items WHERE creator_id = ?');
        $statement->execute([$creatorId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function payoutHistory(int $creatorId): array
    {
        $statement = $this->pdo->prepare('SELECT p.*, a.username AS admin_name FROM payout_requests p
            LEFT JOIN users a ON a.id = p.admin_id WHERE p.creator_id = ? ORDER BY p.created_at DESC, p.id DESC');
        $statement->execute([$creatorId]);

        return $statement->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @return list<array<string, mixed>>
     */
    private function withItems(array $orders): array
    {
        if ($orders === []) {
            return [];
        }
        $ids = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare("SELECT oi.order_id, oi.model_id, oi.price, m.title AS model_title, m.thumb AS model_thumb, u.username AS creator_name
            FROM order_items oi JOIN models m ON m.id = oi.model_id JOIN users u ON u.id = oi.creator_id
            WHERE oi.order_id IN ({$marks}) ORDER BY oi.id ASC");
        $statement->execute($ids);
        $items = [];
        foreach ($statement->fetchAll() as $item) {
            $items[(int) $item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $items[(int) $order['id']] ?? [];
        }
        unset($order);

        return $orders;
    }
}
