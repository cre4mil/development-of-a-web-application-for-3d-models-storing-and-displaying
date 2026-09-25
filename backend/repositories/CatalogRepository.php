<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

/** Read/write access to models, tags and their aggregate counters. */
final class CatalogRepository
{
    private const LIST_SELECT = 'SELECT m.id, m.user_id, m.title, m.filename, m.file_glb, m.thumb, m.description, m.is_public, m.license,
        m.price, m.view_count, m.created_at, u.username AS uploader,
        (SELECT COUNT(*) FROM likes WHERE model_id = m.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE model_id = m.id) AS comment_count';

    private const DATE_WINDOWS = ['week' => 7, 'month' => 30, 'year' => 365];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT m.*, u.username AS uploader,
                (SELECT COUNT(*) FROM likes WHERE model_id = m.id) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE model_id = m.id) AS comment_count
            FROM models m JOIN users u ON u.id = m.user_id WHERE m.id = ?');
        $statement->execute([$id]);
        $model = $statement->fetch();

        return $model === false ? null : $model;
    }

    /**
     * Public gallery search.
     *
     * @param array{search?: string, tag?: string, license?: string, date?: string, sort?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters, int $page, int $perPage): array
    {
        $search = $filters['search'] ?? '';
        $where = ['m.is_public = 1'];
        $params = [];
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(m.title LIKE ? OR m.description LIKE ? OR EXISTS (SELECT 1 FROM model_tags t1 JOIN tags g1 ON g1.id = t1.tag_id WHERE t1.model_id = m.id AND g1.name LIKE ?))';
            array_push($params, $like, $like, $like);
        }
        if (($filters['tag'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM model_tags t2 JOIN tags g2 ON g2.id = t2.tag_id WHERE t2.model_id = m.id AND g2.name = ?)';
            $params[] = $filters['tag'];
        }
        if (($filters['license'] ?? '') !== '') {
            $where[] = 'm.license = ?';
            $params[] = $filters['license'];
        }
        $days = self::DATE_WINDOWS[$filters['date'] ?? ''] ?? null;
        if ($days !== null) {
            $where[] = 'm.created_at >= ?';
            $params[] = date('Y-m-d H:i:s', time() - $days * 86400);
        }
        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM models m WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $orderParams = [];
        $sort = $filters['sort'] ?? '';
        if ($sort === 'likes') {
            $order = 'like_count DESC, m.created_at DESC';
        } elseif ($sort === 'views') {
            $order = 'm.view_count DESC, m.created_at DESC';
        } elseif ($search !== '' && $sort !== 'recent') {
            $order = 'CASE WHEN m.title LIKE ? THEN 3 WHEN m.description LIKE ? THEN 2 ELSE 1 END DESC, m.created_at DESC';
            $orderParams = ['%' . $search . '%', '%' . $search . '%'];
        } else {
            $order = 'm.created_at DESC, m.id DESC';
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->pdo->prepare(self::LIST_SELECT . " FROM models m JOIN users u ON u.id = m.user_id
            WHERE {$whereSql} ORDER BY {$order} LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset);
        $rows->execute([...$params, ...$orderParams]);

        return ['rows' => $rows->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param list<int> $modelIds
     * @return array<int, list<string>>
     */
    public function tagsFor(array $modelIds): array
    {
        if ($modelIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($modelIds), '?'));
        $statement = $this->pdo->prepare("SELECT mt.model_id, t.name FROM model_tags mt JOIN tags t ON t.id = mt.tag_id WHERE mt.model_id IN ({$marks}) ORDER BY t.name");
        $statement->execute($modelIds);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['model_id']][] = (string) $row['name'];
        }

        return $map;
    }

    /** @return list<string> */
    public function tagNames(int $modelId): array
    {
        return $this->tagsFor([$modelId])[$modelId] ?? [];
    }

    /** @return list<string> */
    public function allTags(): array
    {
        return $this->pdo->query('SELECT name FROM tags WHERE id IN (SELECT tag_id FROM model_tags) ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<array<string, mixed>> */
    public function suggested(int $modelId, int $limit): array
    {
        $statement = $this->pdo->prepare('SELECT m.id, m.title, m.thumb, m.view_count, m.price, u.username AS uploader,
                (SELECT COUNT(*) FROM likes WHERE model_id = m.id) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE model_id = m.id) AS comment_count,
                (SELECT COUNT(*) FROM model_tags a JOIN model_tags b ON a.tag_id = b.tag_id WHERE a.model_id = m.id AND b.model_id = ?) AS shared_tags
            FROM models m JOIN users u ON u.id = m.user_id
            WHERE m.is_public = 1 AND m.id <> ?
            ORDER BY shared_tags DESC, m.view_count DESC, m.created_at DESC LIMIT ' . (int) $limit);
        $statement->execute([$modelId, $modelId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $this->pdo->prepare('INSERT INTO models (user_id, title, filename, thumb, description, license, price) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$data['user_id'], $data['title'], $data['filename'], $data['thumb'], $data['description'], $data['license'], $data['price']]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->pdo->prepare('UPDATE models SET title = ?, description = ?, filename = ?, thumb = ?, license = ?, price = ? WHERE id = ?')
            ->execute([$data['title'], $data['description'], $data['filename'], $data['thumb'], $data['license'], $data['price'], $id]);
    }

    public function setConversions(int $id, ?string $gltf, ?string $glb, ?string $usdz, ?string $obj): void
    {
        $this->pdo->prepare('UPDATE models SET file_gltf = ?, file_glb = ?, file_usdz = ?, file_obj = ? WHERE id = ?')
            ->execute([$gltf, $glb, $usdz, $obj, $id]);
    }

    public function setVisibility(int $id, bool $public): void
    {
        $this->pdo->prepare('UPDATE models SET is_public = ? WHERE id = ?')->execute([$public ? 1 : 0, $id]);
    }

    public function incrementViews(int $id): void
    {
        $this->pdo->prepare('UPDATE models SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);
    }

    /** @param list<string> $tags normalized tag names */
    public function syncTags(int $modelId, array $tags): void
    {
        $this->pdo->prepare('DELETE FROM model_tags WHERE model_id = ?')->execute([$modelId]);
        $ignore = Database::insertIgnore($this->pdo);
        $insertTag = $this->pdo->prepare("{$ignore} INTO tags (name) VALUES (?)");
        $selectTag = $this->pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $link = $this->pdo->prepare("{$ignore} INTO model_tags (model_id, tag_id) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $insertTag->execute([$tag]);
            $selectTag->execute([$tag]);
            $link->execute([$modelId, $selectTag->fetchColumn()]);
        }
    }

    public function hasSales(int $modelId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM order_items WHERE model_id = ? LIMIT 1');
        $statement->execute([$modelId]);

        return $statement->fetchColumn() !== false;
    }

    /** Removes a model and everything that hangs off it. Callers must check hasSales() first. */
    public function delete(int $modelId): void
    {
        foreach (['likes', 'collections', 'comments', 'model_tags'] as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE model_id = ?")->execute([$modelId]);
        }
        $this->pdo->prepare('DELETE FROM models WHERE id = ?')->execute([$modelId]);
    }

    /** @return list<array<string, mixed>> */
    public function ownedBy(int $userId): array
    {
        $statement = $this->pdo->prepare(self::LIST_SELECT . ' FROM models m JOIN users u ON u.id = m.user_id WHERE m.user_id = ? ORDER BY m.created_at DESC, m.id DESC');
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function savedBy(int $userId): array
    {
        $statement = $this->pdo->prepare(self::LIST_SELECT . ' FROM models m
            JOIN users u ON u.id = m.user_id JOIN collections c ON c.model_id = m.id
            WHERE c.user_id = ? AND (m.is_public = 1 OR m.user_id = ?) ORDER BY c.created_at DESC');
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function likedBy(int $userId): array
    {
        $statement = $this->pdo->prepare(self::LIST_SELECT . ' FROM models m
            JOIN users u ON u.id = m.user_id JOIN likes l ON l.model_id = m.id
            WHERE l.user_id = ? AND (m.is_public = 1 OR m.user_id = ?) ORDER BY l.created_at DESC');
        $statement->execute([$userId, $userId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function adminList(): array
    {
        return $this->pdo->query('SELECT m.id, m.title, m.is_public, m.price, u.username AS uploader FROM models m JOIN users u ON u.id = m.user_id ORDER BY m.id DESC')->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM models')->fetchColumn();
    }
}
