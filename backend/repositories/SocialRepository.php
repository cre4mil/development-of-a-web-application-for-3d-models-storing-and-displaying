<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/** Likes, saved collections, follows and comments. */
final class SocialRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Flips a like and returns the new state. */
    public function toggleLike(int $userId, int $modelId): bool
    {
        return $this->toggle('likes', $userId, $modelId);
    }

    /** Flips a saved-model entry and returns the new state. */
    public function toggleCollection(int $userId, int $modelId): bool
    {
        return $this->toggle('collections', $userId, $modelId);
    }

    public function likeCount(int $modelId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM likes WHERE model_id = ?');
        $statement->execute([$modelId]);

        return (int) $statement->fetchColumn();
    }

    public function isLiked(int $userId, int $modelId): bool
    {
        return $this->exists('likes', $userId, $modelId);
    }

    public function isSaved(int $userId, int $modelId): bool
    {
        return $this->exists('collections', $userId, $modelId);
    }

    /** @return list<int> */
    public function likedIds(int $userId): array
    {
        return $this->ids('likes', $userId);
    }

    /** @return list<int> */
    public function savedIds(int $userId): array
    {
        return $this->ids('collections', $userId);
    }

    /** Flips a follow and returns the new state. */
    public function toggleFollow(int $followerId, int $followeeId): bool
    {
        $following = $this->isFollowing($followerId, $followeeId);
        if ($following) {
            $this->pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND followee_id = ?')->execute([$followerId, $followeeId]);
        } else {
            $this->pdo->prepare('INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)')->execute([$followerId, $followeeId]);
        }

        return !$following;
    }

    public function isFollowing(int $followerId, int $followeeId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?');
        $statement->execute([$followerId, $followeeId]);

        return $statement->fetchColumn() !== false;
    }

    public function followerCount(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM follows WHERE followee_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function comments(int $modelId): array
    {
        $statement = $this->pdo->prepare('SELECT c.id, c.body, c.created_at, c.user_id, u.username
            FROM comments c JOIN users u ON u.id = c.user_id WHERE c.model_id = ? ORDER BY c.created_at ASC, c.id ASC');
        $statement->execute([$modelId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed> the stored comment with its author */
    public function addComment(int $modelId, int $userId, string $body): array
    {
        $this->pdo->prepare('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)')->execute([$modelId, $userId, $body]);
        $statement = $this->pdo->prepare('SELECT c.id, c.body, c.created_at, c.user_id, u.username
            FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?');
        $statement->execute([$this->pdo->lastInsertId()]);

        return $statement->fetch();
    }

    /** Deletes a comment written by $userId (or any comment when $anyAuthor); returns whether a row was removed. */
    public function deleteComment(int $commentId, int $userId, bool $anyAuthor): bool
    {
        $statement = $anyAuthor
            ? $this->pdo->prepare('DELETE FROM comments WHERE id = ?')
            : $this->pdo->prepare('DELETE FROM comments WHERE id = ? AND user_id = ?');
        $statement->execute($anyAuthor ? [$commentId] : [$commentId, $userId]);

        return $statement->rowCount() > 0;
    }

    private function toggle(string $table, int $userId, int $modelId): bool
    {
        $active = $this->exists($table, $userId, $modelId);
        if ($active) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = ? AND model_id = ?")->execute([$userId, $modelId]);
        } else {
            $this->pdo->prepare("INSERT INTO {$table} (user_id, model_id) VALUES (?, ?)")->execute([$userId, $modelId]);
        }

        return !$active;
    }

    private function exists(string $table, int $userId, int $modelId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE user_id = ? AND model_id = ?");
        $statement->execute([$userId, $modelId]);

        return $statement->fetchColumn() !== false;
    }

    /** @return list<int> */
    private function ids(string $table, int $userId): array
    {
        $statement = $this->pdo->prepare("SELECT model_id FROM {$table} WHERE user_id = ?");
        $statement->execute([$userId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
