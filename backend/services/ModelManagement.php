<?php

declare(strict_types=1);

namespace App\Services;

final class ModelManagement
{
    public const DEFAULT_LICENSE = 'CC BY';

    /** License code => [description, URL with the legal text (null when none applies)]. */
    public const LICENSES = [
        self::DEFAULT_LICENSE => ['Creative Commons Attribution 4.0', 'https://creativecommons.org/licenses/by/4.0/'],
        'CC BY-SA' => ['CC Attribution-ShareAlike 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        'CC BY-ND' => ['CC Attribution-NoDerivs 4.0', 'https://creativecommons.org/licenses/by-nd/4.0/'],
        'CC BY-NC' => ['CC Attribution-NonCommercial 4.0', 'https://creativecommons.org/licenses/by-nc/4.0/'],
        'CC BY-NC-SA' => ['CC Attribution-NonCommercial-ShareAlike 4.0', 'https://creativecommons.org/licenses/by-nc-sa/4.0/'],
        'CC BY-NC-ND' => ['CC Attribution-NonCommercial-NoDerivs 4.0', 'https://creativecommons.org/licenses/by-nc-nd/4.0/'],
        'CC0' => ['Public Domain (CC0)', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'All Rights Reserved' => ['All Rights Reserved', null],
    ];

    /** @return list<string> */
    public static function licenseCodes(): array
    {
        return array_keys(self::LICENSES);
    }

    /** @return array{name: string, url: ?string} */
    public static function licenseInfo(string $license): array
    {
        [$name, $url] = self::LICENSES[$license] ?? self::LICENSES['All Rights Reserved'];

        return ['name' => $name, 'url' => $url];
    }

    public static function isOwner(int $userId, int $ownerId): bool
    {
        return $userId > 0 && $userId === $ownerId;
    }

    /**
     * Public models are visible to everyone; hidden ones only to their owner and admins.
     *
     * @param array<string, mixed>|null $model
     */
    public static function canView(?array $model, int $userId, bool $isAdmin): bool
    {
        return $model !== null
            && ((int) $model['is_public'] === 1 || $isAdmin || self::isOwner($userId, (int) $model['user_id']));
    }

    public static function normalizePrice(mixed $price): float
    {
        return max(0, round((float) $price, 2));
    }

    public static function normalizeLicense(string $license): string
    {
        return isset(self::LICENSES[$license]) ? $license : self::DEFAULT_LICENSE;
    }

    /** @return list<string> */
    public static function normalizeTags(string $rawTags): array
    {
        $tags = array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), explode(',', $rawTags));
        $tags = array_filter($tags, static fn (string $tag): bool => $tag !== '');
        $tags = array_map(static fn (string $tag): string => mb_substr($tag, 0, 50), $tags);

        return array_values(array_unique($tags));
    }

    /** "Old Telephone" + "abc.glb" => "Old_Telephone.glb". */
    public static function downloadName(string $title, string $storedName): string
    {
        $slug = trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '_', $title), '_');

        return ($slug === '' ? 'model' : $slug) . '.' . strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
    }

    /**
     * Picks the file the 3D viewer should load: the GLB conversion when present (self-contained,
     * textures embedded), otherwise the uploaded original.
     *
     * @param array<string, mixed> $model
     * @return array{file: string, ext: string}
     */
    public static function viewerFile(array $model, ModelFiles $files): array
    {
        $glb = (string) ($model['file_glb'] ?? '');
        $file = $files->exists($glb) ? $glb : (string) $model['filename'];

        return ['file' => $file, 'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION))];
    }

    /**
     * Downloadable formats that actually exist on disk.
     *
     * @param array<string, mixed> $model
     * @return list<array{format: string, ext: string, size: int, label: string}>
     */
    public static function downloadFormats(array $model, ModelFiles $files): array
    {
        $formats = [];
        if ($files->exists((string) $model['filename'])) {
            $formats[] = [
                'format' => 'original',
                'ext' => strtoupper(pathinfo((string) $model['filename'], PATHINFO_EXTENSION)),
                'size' => $files->size((string) $model['filename']),
                'label' => 'Original format',
            ];
        }
        foreach (['gltf' => 'file_gltf', 'glb' => 'file_glb', 'usdz' => 'file_usdz', 'obj' => 'file_obj'] as $format => $column) {
            $name = (string) ($model[$column] ?? '');
            if ($name !== '' && $name !== $model['filename'] && $files->exists($name)) {
                $formats[] = ['format' => $format, 'ext' => strtoupper($format), 'size' => $files->size($name), 'label' => 'Converted format'];
            }
        }

        return $formats;
    }
}
