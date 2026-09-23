<?php

declare(strict_types=1);

namespace App\Repositories;

interface OrderStore
{
    /** @param list<int> $modelIds @return list<array<string, mixed>> */
    public function purchasableModels(array $modelIds): array;

    public function hasActiveOrder(int $buyerId, int $modelId): bool;

    public function platformFeePercent(float $default): float;

    /** @param list<array<string, int|float>> $items */
    public function createOrder(string $reference, int $buyerId, float $total, string $slipUrl, string $status, ?string $note, array $items): int;

    /** @return array<string, mixed>|null */
    public function findOrder(int $orderId): ?array;

    public function isAdmin(int $userId): bool;

    public function updateStatus(int $orderId, string $status, ?string $note, int $adminId): void;

    /** @return list<array{creator_id: int, earn: float}> */
    public function earningsForOrder(int $orderId): array;

    public function creditWallet(int $creatorId, float $amount): void;

    public function transaction(callable $operation): mixed;
}
