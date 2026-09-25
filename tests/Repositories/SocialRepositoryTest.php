<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SocialRepository;
use Tests\Support\AppTestCase;

final class SocialRepositoryTest extends AppTestCase
{
    private SocialRepository $social;
    private int $alice;
    private int $bob;
    private int $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->social = new SocialRepository($this->pdo);
        $this->alice = $this->addUser('alice');
        $this->bob = $this->addUser('bob');
        $this->model = $this->addModel($this->bob);
    }

    public function testLikeToggleAndCounters(): void
    {
        self::assertFalse($this->social->isLiked($this->alice, $this->model));
        self::assertTrue($this->social->toggleLike($this->alice, $this->model));
        self::assertTrue($this->social->toggleLike($this->bob, $this->model));
        self::assertTrue($this->social->isLiked($this->alice, $this->model));
        self::assertSame(2, $this->social->likeCount($this->model));
        self::assertSame([$this->model], $this->social->likedIds($this->alice));

        self::assertFalse($this->social->toggleLike($this->alice, $this->model));
        self::assertSame(1, $this->social->likeCount($this->model));
        self::assertSame([], $this->social->likedIds($this->alice));
    }

    public function testCollectionToggle(): void
    {
        self::assertTrue($this->social->toggleCollection($this->alice, $this->model));
        self::assertTrue($this->social->isSaved($this->alice, $this->model));
        self::assertSame([$this->model], $this->social->savedIds($this->alice));
        self::assertFalse($this->social->toggleCollection($this->alice, $this->model));
        self::assertFalse($this->social->isSaved($this->alice, $this->model));
    }

    public function testFollowToggleAndFollowerCount(): void
    {
        self::assertTrue($this->social->toggleFollow($this->alice, $this->bob));
        self::assertTrue($this->social->isFollowing($this->alice, $this->bob));
        self::assertFalse($this->social->isFollowing($this->bob, $this->alice));
        self::assertSame(1, $this->social->followerCount($this->bob));

        self::assertFalse($this->social->toggleFollow($this->alice, $this->bob));
        self::assertSame(0, $this->social->followerCount($this->bob));
    }

    public function testCommentsRoundTrip(): void
    {
        $first = $this->social->addComment($this->model, $this->alice, 'first');
        $this->social->addComment($this->model, $this->bob, 'second');

        self::assertSame('alice', $first['username']);
        self::assertSame('first', $first['body']);
        self::assertSame(['first', 'second'], array_column($this->social->comments($this->model), 'body'));
        self::assertSame([], $this->social->comments(999));
    }

    public function testCommentDeletionRules(): void
    {
        $comment = (int) $this->social->addComment($this->model, $this->alice, 'mine')['id'];

        self::assertFalse($this->social->deleteComment($comment, $this->bob, false), 'other users cannot delete it');
        self::assertTrue($this->social->deleteComment($comment, $this->alice, false));
        self::assertFalse($this->social->deleteComment($comment, $this->alice, false), 'already gone');

        $again = (int) $this->social->addComment($this->model, $this->alice, 'again')['id'];
        self::assertTrue($this->social->deleteComment($again, $this->bob, true), 'moderators may delete any comment');
    }
}
