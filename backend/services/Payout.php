<?php

declare(strict_types=1);

namespace App\Services;

final class Payout
{
    public static function canCreate(float $availableBalance, float $amount): bool
    {
        return $amount > 0
            && round($amount, 2) === $amount
            && $availableBalance >= $amount;
    }

    public static function canTransition(string $currentStatus, string $newStatus): bool
    {
        return $currentStatus === 'pending'
            && in_array($newStatus, ['transferred', 'rejected'], true);
    }

    public static function isValidPlatformFee(float $feePercent): bool
    {
        return $feePercent >= 0 && $feePercent <= 100;
    }
}
