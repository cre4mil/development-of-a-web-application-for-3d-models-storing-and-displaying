<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PayoutStore;
use App\Services\PayoutService;
use PHPUnit\Framework\TestCase;

final class PayoutServiceTest extends TestCase
{
    public function testCreatesPayoutAndMovesFundsToPending(): void
    {
        $store = new InMemoryPayoutStore(500.00);
        $service = new PayoutService($store);

        $this->assertSame(1, $service->create(10, 125.50, 1));
        $this->assertSame(374.50, $store->available);
        $this->assertSame(125.50, $store->pending);
        $this->assertSame('pending', $store->payouts[1]['status']);
    }

    public function testDoesNotCreatePayoutWhenBalanceIsInsufficient(): void
    {
        $store = new InMemoryPayoutStore(100.00);
        $service = new PayoutService($store);

        $this->assertNull($service->create(10, 100.01, 1));
        $this->assertSame(100.00, $store->available);
        $this->assertSame([], $store->payouts);
    }

    public function testTransferSettlesPendingBalanceOnce(): void
    {
        $store = new InMemoryPayoutStore(0.00, [1 => ['creator_id' => 10, 'amount' => 50.00, 'status' => 'pending']]);
        $store->pending = 50.00;
        $service = new PayoutService($store);

        $this->assertTrue($service->transfer(1, 1, 'uploads/payout_slips/proof.png'));
        $this->assertSame('transferred', $store->payouts[1]['status']);
        $this->assertSame(0.00, $store->pending);
        $this->assertFalse($service->transfer(1, 1, 'uploads/payout_slips/proof.png'));
    }

    public function testRejectReturnsFundsToAvailableBalance(): void
    {
        $store = new InMemoryPayoutStore(10.00, [1 => ['creator_id' => 10, 'amount' => 40.00, 'status' => 'pending']]);
        $store->pending = 40.00;
        $service = new PayoutService($store);

        $this->assertTrue($service->reject(1, 'บัญชีไม่ถูกต้อง'));
        $this->assertSame('rejected', $store->payouts[1]['status']);
        $this->assertSame(50.00, $store->available);
        $this->assertSame(0.00, $store->pending);
    }
}

final class InMemoryPayoutStore implements PayoutStore
{
    public float $available;
    public float $pending = 0.0;
    /** @var array<int, array<string, mixed>> */
    public array $payouts;

    public function __construct(float $available, array $payouts = [])
    {
        $this->available = $available;
        $this->payouts = $payouts;
    }

    public function availableBalance(int $creatorId): ?float { return $this->available; }
    public function createRequest(int $creatorId, float $amount, int $adminId): int
    {
        $id = count($this->payouts) + 1;
        $this->payouts[$id] = compact('creatorId', 'amount') + ['creator_id' => $creatorId, 'status' => 'pending'];
        return $id;
    }
    public function findRequest(int $payoutId): ?array { return $this->payouts[$payoutId] ?? null; }
    public function markTransferred(int $payoutId, int $adminId, string $slipPath): void { $this->payouts[$payoutId]['status'] = 'transferred'; }
    public function markRejected(int $payoutId, string $note): void { $this->payouts[$payoutId]['status'] = 'rejected'; }
    public function moveToPending(int $creatorId, float $amount): void { $this->available -= $amount; $this->pending += $amount; }
    public function settlePending(int $creatorId, float $amount): void { $this->pending -= $amount; }
    public function restorePending(int $creatorId, float $amount): void { $this->available += $amount; $this->pending -= $amount; }
    public function transaction(callable $operation): mixed { return $operation(); }
}
