<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PayoutStore;

final class PayoutService
{
    public function __construct(private readonly PayoutStore $store)
    {
    }

    public function create(int $creatorId, float $amount, int $adminId): ?int
    {
        $balance = $this->store->availableBalance($creatorId);
        if ($balance === null || !Payout::canCreate($balance, $amount)) {
            return null;
        }

        return $this->store->transaction(function () use ($creatorId, $amount, $adminId): int {
            $this->store->moveToPending($creatorId, $amount);
            return $this->store->createRequest($creatorId, $amount, $adminId);
        });
    }

    public function transfer(int $payoutId, int $adminId, string $slipPath): bool
    {
        $payout = $this->store->findRequest($payoutId);
        if ($payout === null || !Payout::canTransition((string) $payout['status'], 'transferred')) {
            return false;
        }

        $this->store->transaction(function () use ($payout, $payoutId, $adminId, $slipPath): void {
            $this->store->markTransferred($payoutId, $adminId, $slipPath);
            $this->store->settlePending((int) $payout['creator_id'], (float) $payout['amount']);
        });

        return true;
    }

    public function reject(int $payoutId, string $note): bool
    {
        $payout = $this->store->findRequest($payoutId);
        if ($payout === null || !Payout::canTransition((string) $payout['status'], 'rejected')) {
            return false;
        }

        $this->store->transaction(function () use ($payout, $payoutId, $note): void {
            $this->store->markRejected($payoutId, $note);
            $this->store->restorePending((int) $payout['creator_id'], (float) $payout['amount']);
        });

        return true;
    }
}
