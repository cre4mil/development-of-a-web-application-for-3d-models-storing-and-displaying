<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OrderStore;
use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

final class OrderServiceTest extends TestCase
{
    public function testCreatesOrderForValidPaidModelsAndCreditsOnlyWhenApproved(): void
    {
        $store = new InMemoryOrderStore();
        $service = new OrderService($store);

        $order = $service->create(1, [10, 11, 12], 'uploads/slips/a.png', 'approved', null, 10);

        $this->assertSame(2, $order['items']);
        $this->assertSame(150.00, $order['total']);
        $this->assertSame(135.00, $store->walletCredits[2]);
    }

    public function testRejectsCartWhenEveryItemIsOwnedOrAlreadyPurchased(): void
    {
        $store = new InMemoryOrderStore();
        $store->activeModels = [11];
        $service = new OrderService($store);

        $this->assertNull($service->create(1, [11, 12], 'uploads/slips/a.png', 'pending', null, 10));
    }

    public function testOnlyAdminCanProcessPendingOrderAndCreditsOnce(): void
    {
        $store = new InMemoryOrderStore();
        $store->orders[5] = ['id' => 5, 'payment_status' => 'pending'];
        $store->earnings[5] = [['creator_id' => 2, 'earn' => 90.00]];
        $service = new OrderService($store);

        $this->assertSame('forbidden', $service->process(99, 5, 'approved', ''));
        $this->assertSame('order_not_found', $service->process(1, 404, 'approved', ''));
        $this->assertSame('ok', $service->process(1, 5, 'approved', 'verified'));
        $this->assertSame(90.00, $store->walletCredits[2]);
        $this->assertSame('already_processed', $service->process(1, 5, 'approved', ''));
    }
}

final class InMemoryOrderStore implements OrderStore
{
    public array $activeModels = [];
    public array $orders = [];
    public array $earnings = [];
    public array $walletCredits = [];
    private array $models = [
        ['id' => 10, 'user_id' => 2, 'price' => 100.00],
        ['id' => 11, 'user_id' => 2, 'price' => 50.00],
        ['id' => 12, 'user_id' => 1, 'price' => 30.00],
    ];

    public function purchasableModels(array $modelIds): array { return array_values(array_filter($this->models, fn (array $model): bool => in_array($model['id'], $modelIds, true))); }
    public function hasActiveOrder(int $buyerId, int $modelId): bool { return in_array($modelId, $this->activeModels, true); }
    public function platformFeePercent(float $default): float { return $default; }
    public function createOrder(string $reference, int $buyerId, float $total, string $slipUrl, string $status, ?string $note, array $items): int { $id = count($this->orders) + 1; $this->orders[$id] = ['id' => $id, 'payment_status' => $status]; $this->earnings[$id] = array_map(fn (array $item): array => ['creator_id' => $item['creator_id'], 'earn' => $item['creator_earning']], $items); return $id; }
    public function findOrder(int $orderId): ?array { return $this->orders[$orderId] ?? null; }
    public function isAdmin(int $userId): bool { return $userId === 1; }
    public function updateStatus(int $orderId, string $status, ?string $note, int $adminId): void { $this->orders[$orderId]['payment_status'] = $status; }
    public function earningsForOrder(int $orderId): array { return $this->earnings[$orderId] ?? []; }
    public function creditWallet(int $creatorId, float $amount): void { $this->walletCredits[$creatorId] = ($this->walletCredits[$creatorId] ?? 0) + $amount; }
    public function transaction(callable $operation): mixed { return $operation(); }
}
