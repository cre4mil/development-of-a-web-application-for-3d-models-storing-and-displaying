<?php

declare(strict_types=1);

namespace App\Services;

// กฎการจ่ายเงินครีเอเตอร์
final class Payout
{
    // ยอดต้องมากกว่า 0 ทศนิยมไม่เกิน 2 ตำแหน่ง และไม่เกินยอดคงเหลือ
    public static function canCreate(float $availableBalance, float $amount): bool
    {
        return $amount > 0
            && round($amount, 2) === $amount
            && $availableBalance >= $amount;
    }

    // เปลี่ยนสถานะได้เฉพาะจาก pending เป็น transferred/rejected
    public static function canTransition(string $currentStatus, string $newStatus): bool
    {
        return $currentStatus === 'pending'
            && in_array($newStatus, ['transferred', 'rejected'], true);
    }

    // ค่าธรรมเนียมต้องอยู่ระหว่าง 0–100%
    public static function isValidPlatformFee(float $feePercent): bool
    {
        return $feePercent >= 0 && $feePercent <= 100;
    }
}
