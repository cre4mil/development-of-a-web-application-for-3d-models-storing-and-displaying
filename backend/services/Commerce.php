<?php

declare(strict_types=1);

namespace App\Services;

// กฎการซื้อขาย: ตรวจรายการโมเดล สลิป คำนวณรายได้ และสถานะออเดอร์
final class Commerce
{
    public const MAX_SLIP_BYTES = 5_242_880;

    // แปลงรายการ id โมเดลให้เป็นตัวเลขที่ถูกต้องและไม่ซ้ำ
    /** @param array<int, mixed> $ids */
    public static function normalizeModelIds(array $ids): array
    {
        $normalized = array_map(static fn ($id): int => (int) $id, $ids);
        $normalized = array_filter($normalized, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($normalized));
    }

    // ตรวจไฟล์สลิป (ต้องมี เป็นรูป และไม่เกิน 5 MB) คืนรหัส error หรือ null
    /** @param array<string, mixed>|null $file */
    public static function validateSlip(?array $file): ?string
    {
        $error = null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'slip_required';
        } elseif (!Security::isAllowedUploadExtension((string) ($file['name'] ?? ''), 'image')) {
            $error = 'slip_format';
        } elseif ((int) ($file['size'] ?? 0) > self::MAX_SLIP_BYTES) {
            $error = 'slip_too_large';
        }

        return $error;
    }

    // แบ่งราคาเป็นค่าธรรมเนียมแพลตฟอร์มกับรายได้ครีเอเตอร์
    /** @return array{platform_fee: float, creator_earning: float} */
    public static function calculateEarnings(float $price, float $feePercent): array
    {
        $price = max(0, round($price, 2));
        $feePercent = min(100, max(0, $feePercent));
        $fee = round($price * ($feePercent / 100), 2);

        return [
            'platform_fee' => $fee,
            'creator_earning' => round($price - $fee, 2),
        ];
    }

    // เปลี่ยนสถานะออเดอร์ได้เฉพาะจาก pending เป็น approved/rejected
    public static function canProcessOrder(string $currentStatus, string $newStatus): bool
    {
        return $currentStatus === 'pending'
            && in_array($newStatus, ['approved', 'rejected'], true);
    }
}
