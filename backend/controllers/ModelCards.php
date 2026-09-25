<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CatalogRepository;
use App\Repositories\SocialRepository;
use App\Services\ModelFiles;
use App\Services\ModelManagement;

/** Enriches catalog rows with everything the gallery card template needs. */
final class ModelCards
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function prepare(array $rows, CatalogRepository $catalog, SocialRepository $social, ModelFiles $files, int $userId): array
    {
        $tags = $catalog->tagsFor(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $liked = $userId > 0 ? $social->likedIds($userId) : [];
        $saved = $userId > 0 ? $social->savedIds($userId) : [];

        return array_map(static function (array $row) use ($tags, $liked, $saved, $files, $userId): array {
            $id = (int) $row['id'];
            $viewer = ModelManagement::viewerFile($row, $files);

            return $row + [
                'tags' => $tags[$id] ?? [],
                'liked' => in_array($id, $liked, true),
                'saved' => in_array($id, $saved, true),
                'mine' => $userId > 0 && (int) $row['user_id'] === $userId,
                'viewer_file' => $viewer['file'],
                'viewer_ext' => $viewer['ext'],
                'size' => $files->size($row['filename']),
            ];
        }, $rows);
    }
}
