<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\CommerceQueries;
use Tests\Support\AppTestCase;

final class CommerceQueriesTest extends AppTestCase
{
    private CommerceQueries $queries;
    private int $seller;
    private int $buyer;
    private int $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queries = new CommerceQueries($this->pdo);
        $this->seller = $this->addUser('seller');
        $this->buyer = $this->addUser('buyer');
        $this->model = $this->addModel($this->seller, ['title' => 'Paid', 'price' => 100]);
    }

    private function order(string $ref, string $status, ?string $createdAt = null, float $earning = 90.0): int
    {
        $this->exec('INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status, payment_slip_url, admin_note, created_at) VALUES (?, ?, 100, ?, ?, ?, ?)', $ref, $this->buyer, $status, 'uploads/slips/' . $ref . '.png', $status === 'rejected' ? 'bad slip' : null, $createdAt ?? date('Y-m-d H:i:s'));
        $orderId = (int) $this->pdo->lastInsertId();
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price, platform_fee, creator_earning, created_at) VALUES (?, ?, ?, 100, ?, ?, ?)', $orderId, $this->model, $this->seller, 100 - $earning, $earning, $createdAt ?? date('Y-m-d H:i:s'));

        return $orderId;
    }

    public function testPurchaseStatePrefersApprovedOverPending(): void
    {
        self::assertNull($this->queries->purchaseState($this->buyer, $this->model));

        $this->order('R1', 'rejected');
        self::assertNull($this->queries->purchaseState($this->buyer, $this->model));

        $this->order('R2', 'pending');
        self::assertSame('pending', $this->queries->purchaseState($this->buyer, $this->model));

        $this->order('R3', 'approved');
        self::assertSame('approved', $this->queries->purchaseState($this->buyer, $this->model));
        self::assertNull($this->queries->purchaseState($this->seller, $this->model));
    }

    public function testBuyerOrdersIncludeItems(): void
    {
        $this->order('R1', 'approved');
        $this->order('R2', 'pending');

        $orders = $this->queries->buyerOrders($this->buyer);

        self::assertCount(2, $orders);
        self::assertSame('Paid', $orders[0]['items'][0]['model_title']);
        self::assertSame('seller', $orders[0]['items'][0]['creator_name']);
        self::assertSame([], $this->queries->buyerOrders($this->seller));
    }

    public function testAdminOrdersFilterByStatus(): void
    {
        $this->order('R1', 'approved');
        $this->order('R2', 'pending');
        $this->order('R3', 'rejected');

        self::assertCount(3, $this->queries->adminOrders(''));
        self::assertCount(3, $this->queries->adminOrders('bogus'));
        self::assertCount(1, $this->queries->adminOrders('pending'));
        self::assertSame('buyer', $this->queries->adminOrders('approved')[0]['buyer_name']);
    }

    public function testCountsAndRevenue(): void
    {
        self::assertSame(['pending' => 0, 'approved' => 0, 'rejected' => 0], $this->queries->orderCounts());
        self::assertSame(0.0, $this->queries->revenue());

        $this->order('R1', 'approved');
        $this->order('R2', 'approved');
        $this->order('R3', 'pending');

        self::assertSame(['pending' => 1, 'approved' => 2, 'rejected' => 0], $this->queries->orderCounts());
        self::assertSame(200.0, $this->queries->revenue());
    }

    public function testDashboardOrdersShowTheFirstModelAndItemCount(): void
    {
        $orderId = $this->order('R1', 'pending');
        $second = $this->addModel($this->seller, ['title' => 'Second', 'price' => 5]);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (?, ?, ?, 5)', $orderId, $second, $this->seller);

        $row = $this->queries->dashboardOrders()[0];

        self::assertSame('Paid', $row['model_title']);
        self::assertSame(2, (int) $row['item_count']);
        self::assertSame('buyer', $row['buyer']);
    }

    public function testSalesEarningsAndPayoutHistory(): void
    {
        $recent = date('Y-m-d H:i:s');
        $lastMonth = date('Y-m-d H:i:s', strtotime('-1 month'));
        $ancient = date('Y-m-d H:i:s', strtotime('-2 years'));
        $this->order('R1', 'approved', $recent, 90);
        $this->order('R2', 'approved', $recent, 45.5);
        $this->order('R3', 'approved', $lastMonth, 10);
        $this->order('R4', 'approved', $ancient, 999);
        $this->order('R5', 'pending', $recent, 500);
        $this->exec("INSERT INTO payout_requests (creator_id, amount, admin_id) VALUES (?, 30, ?)", $this->seller, $this->buyer);

        self::assertCount(5, $this->queries->sales($this->seller, 100));
        self::assertCount(2, $this->queries->sales($this->seller, 2));
        self::assertSame(1, $this->queries->soldModelCount($this->seller));

        $monthly = $this->queries->monthlyEarnings($this->seller, 6);
        self::assertCount(2, $monthly);
        self::assertSame(2, $monthly[1]['sales']);
        self::assertSame(135.5, $monthly[1]['earning']);
        self::assertLessThan($monthly[1]['month'], $monthly[0]['month']);

        $history = $this->queries->payoutHistory($this->seller);
        self::assertCount(1, $history);
        self::assertSame('buyer', $history[0]['admin_name']);
    }
}
