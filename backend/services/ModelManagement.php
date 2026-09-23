<?php

declare(strict_types=1);

namespace App\Services;

final class ModelManagement
{
    public const DEFAULT_LICENSE = 'CC BY';

    private const LICENSES = [
        self::DEFAULT_LICENSE, 'CC BY-SA', 'CC BY-ND', 'CC BY-NC',
        'CC BY-NC-SA', 'CC BY-NC-ND', 'CC0', 'All Rights Reserved',
    ];

    public static function isOwner(int $userId, int $ownerId): bool
    {
        return $userId > 0 && $userId === $ownerId;
    }

    public static function normalizePrice(mixed $price): float
    {
        return max(0, round((float) $price, 2));
    }

    public static function normalizeLicense(string $license): string
    {
        return in_array($license, self::LICENSES, true) ? $license : self::DEFAULT_LICENSE;
    }

    /** @return list<string> */
    public static function normalizeTags(string $rawTags): array
    {
        $tags = array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), explode(',', $rawTags));
        $tags = array_filter($tags, static fn (string $tag): bool => $tag !== '');

        return array_values(array_unique($tags));
    }
}
