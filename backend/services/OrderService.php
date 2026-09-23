<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderStore;

final class OrderService
{
    public function __construct(private readonly OrderStore $store)
    {
    }

    /** @param list<int> $modelIds @return array{order_ref: string, items: int, total: float}|null */
    public function create(int $buyerId, array $modelIds, string $slipUrl, string $status, ?string $note, float $defaultFeePercent): ?array
    {
        $quote = $this->quote($buyerId, $modelIds, $defaultFeePercent);
        if ($quote === null) {
            return null;
        }
        $items = $quote['items'];
        $total = $quote['total'];
        $reference = strtoupper(bin2hex(random_bytes(6)));
        $this->store->transaction(function () use ($reference, $buyerId, $total, $slipUrl, $status, $note, $items): void {
            $orderId = $this->store->createOrder($reference, $buyerId, $total, $slipUrl, $status, $note, $items);
            if ($status === 'approved') {
                $this->creditEarnings($orderId);
            }
        });

        return ['order_ref' => $reference, 'items' => count($items), 'total' => $total];
    }

    /** @param list<int> $modelIds @return array{items: list<array<string, int|float>>, total: float}|null */
    public function quote(int $buyerId, array $modelIds, float $defaultFeePercent): ?array
    {
        $models = array_column($this->store->purchasableModels($modelIds), null, 'id');
        $feePercent = $this->store->platformFeePercent($defaultFeePercent);
        $items = [];
        foreach ($modelIds as $modelId) {
            $model = $models[$modelId] ?? null;
            if ($model === null || (int) $model['user_id'] === $buyerId || $this->store->hasActiveOrder($buyerId, $modelId)) {
                continue;
            }
            $earnings = Commerce::calculateEarnings((float) $model['price'], $feePercent);
            $items[] = ['model_id' => $modelId, 'creator_id' => (int) $model['user_id'], 'price' => (float) $model['price']] + $earnings;
        }
        return $items === [] ? null : ['items' => $items, 'total' => round(array_sum(array_column($items, 'price')), 2)];
    }

    public function process(int $adminId, int $orderId, string $status, string $note): string
    {
        $result = 'ok';
        if (!$this->store->isAdmin($adminId)) {
            $result = 'forbidden';
        } else {
            $order = $this->store->findOrder($orderId);
            if ($order === null) {
                $result = 'order_not_found';
            } elseif (!Commerce::canProcessOrder((string) $order['payment_status'], $status)) {
                $result = 'already_processed';
            } else {
                $this->store->transaction(function () use ($adminId, $orderId, $status, $note): void {
                    $this->store->updateStatus($orderId, $status, $note !== '' ? $note : null, $adminId);
                    if ($status === 'approved') {
                        $this->creditEarnings($orderId);
                    }
                });
            }
        }

        return $result;
    }

    private function creditEarnings(int $orderId): void
    {
        foreach ($this->store->earningsForOrder($orderId) as $earning) {
            $this->store->creditWallet((int) $earning['creator_id'], (float) $earning['earn']);
        }
    }
}
