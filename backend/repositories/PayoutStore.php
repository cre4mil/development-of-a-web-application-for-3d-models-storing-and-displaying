<?php

declare(strict_types=1);

namespace App\Repositories;

interface PayoutStore
{
    public function availableBalance(int $creatorId): ?float;

    public function createRequest(int $creatorId, float $amount, int $adminId): int;

    /** @return array<string, mixed>|null */
    public function findRequest(int $payoutId): ?array;

    public function markTransferred(int $payoutId, int $adminId, string $slipPath): void;

    public function markRejected(int $payoutId, string $note): void;

    public function moveToPending(int $creatorId, float $amount): void;

    public function settlePending(int $creatorId, float $amount): void;

    public function restorePending(int $creatorId, float $amount): void;

    public function transaction(callable $operation): mixed;
}
