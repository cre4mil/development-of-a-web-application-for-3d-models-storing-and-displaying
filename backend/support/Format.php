<?php

declare(strict_types=1);

namespace App\Support;

/** Display formatting shared by templates and controllers. */
final class Format
{
    public static function bytes(int|float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    /** 1250 -> "1.3k", 2_400_000 -> "2.4M". */
    public static function compact(int|float $number): string
    {
        $number = (float) $number;
        if ($number >= 1_000_000) {
            return rtrim(rtrim(number_format($number / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($number >= 1_000) {
            return rtrim(rtrim(number_format($number / 1_000, 1), '0'), '.') . 'k';
        }

        return (string) (int) $number;
    }

    public static function money(int|float|string $amount): string
    {
        return '฿' . number_format((float) $amount, 2);
    }

    public static function dateTime(?string $value): string
    {
        $time = $value === null || $value === '' ? false : strtotime($value);

        return $time === false ? '-' : date('d/m/Y H:i', $time);
    }

    public static function initial(string $name): string
    {
        return mb_strtoupper(mb_substr(trim($name), 0, 1, 'UTF-8'), 'UTF-8') ?: '?';
    }
}
