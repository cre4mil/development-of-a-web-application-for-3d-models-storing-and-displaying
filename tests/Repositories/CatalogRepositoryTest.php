<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\CatalogRepository;
use Tests\Support\AppTestCase;

final class CatalogRepositoryTest extends AppTestCase
{
    private CatalogRepository $catalog;
    private int $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new CatalogRepository($this->pdo);
        $this->owner = $this->addUser('owner');
    }

    public function testFindReturnsCountersAndUploader(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Chair']);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $this->owner, $id);
        $this->exec('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)', $id, $this->owner, 'nice');

        $model = $this->catalog->find($id);

        self::assertSame('Chair', $model['title']);
        self::assertSame('owner', $model['uploader']);
        self::assertSame(1, (int) $model['like_count']);
        self::assertSame(1, (int) $model['comment_count']);
        self::assertNull($this->catalog->find(999));
    }

    public function testSearchOnlyReturnsPublicModelsNewestFirst(): void
    {
        $old = $this->addModel($this->owner, ['title' => 'Old', 'created_at' => '2024-01-01 00:00:00']);
        $new = $this->addModel($this->owner, ['title' => 'New', 'created_at' => '2025-01-01 00:00:00']);
        $this->addModel($this->owner, ['title' => 'Hidden', 'is_public' => 0]);

        $result = $this->catalog->search([], 1, 10);

        self::assertSame(2, $result['total']);
        self::assertSame([$new, $old], array_map(static fn (array $row): int => (int) $row['id'], $result['rows']));
        self::assertSame(1, $result['pages']);
    }

    public function testSearchMatchesTitleDescriptionAndTags(): void
    {
        $byTitle = $this->addModel($this->owner, ['title' => 'Red chair', 'description' => 'x']);
        $byDescription = $this->addModel($this->owner, ['title' => 'Other', 'description' => 'a red thing']);
        $byTag = $this->addModel($this->owner, ['title' => 'Third', 'description' => 'nothing']);
        $this->addTags($byTag, 'red');
        $this->addModel($this->owner, ['title' => 'No match', 'description' => 'blue']);

        $result = $this->catalog->search(['search' => 'red'], 1, 10);

        self::assertSame(3, $result['total']);
        self::assertSame($byTitle, (int) $result['rows'][0]['id'], 'a title hit ranks first');
        self::assertSame($byDescription, (int) $result['rows'][1]['id'], 'a description hit ranks second');
        self::assertSame($byTag, (int) $result['rows'][2]['id']);
    }

    public function testRecentSortOverridesRelevanceRanking(): void
    {
        $this->addModel($this->owner, ['title' => 'Chair title', 'created_at' => '2024-01-01 00:00:00']);
        $newer = $this->addModel($this->owner, ['title' => 'Other', 'description' => 'chair inside', 'created_at' => '2025-01-01 00:00:00']);

        $rows = $this->catalog->search(['search' => 'chair', 'sort' => 'recent'], 1, 10)['rows'];

        self::assertSame($newer, (int) $rows[0]['id']);
    }

    public function testFiltersByTagLicenseAndDate(): void
    {
        $tagged = $this->addModel($this->owner, ['license' => 'CC0']);
        $this->addTags($tagged, 'vehicle');
        $this->addModel($this->owner, ['license' => 'CC BY']);
        $this->addModel($this->owner, ['license' => 'CC0', 'created_at' => date('Y-m-d H:i:s', strtotime('-40 days'))]);

        self::assertSame(1, $this->catalog->search(['tag' => 'vehicle'], 1, 10)['total']);
        self::assertSame(2, $this->catalog->search(['license' => 'CC0'], 1, 10)['total']);
        self::assertSame(2, $this->catalog->search(['date' => 'week'], 1, 10)['total']);
        self::assertSame(2, $this->catalog->search(['date' => 'month'], 1, 10)['total']);
        self::assertSame(3, $this->catalog->search(['date' => 'year'], 1, 10)['total']);
        self::assertSame(3, $this->catalog->search(['date' => 'bogus'], 1, 10)['total']);
    }

    public function testSortsByLikesAndViews(): void
    {
        $popular = $this->addModel($this->owner, ['view_count' => 1]);
        $viewed = $this->addModel($this->owner, ['view_count' => 500]);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $this->owner, $popular);

        $byLikes = $this->catalog->search(['sort' => 'likes'], 1, 10)['rows'];
        $byViews = $this->catalog->search(['sort' => 'views'], 1, 10)['rows'];

        self::assertSame($popular, (int) $byLikes[0]['id']);
        self::assertSame($viewed, (int) $byViews[0]['id']);
    }

    public function testPaginationClampsThePageNumber(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->addModel($this->owner);
        }

        $second = $this->catalog->search([], 2, 2);
        $tooFar = $this->catalog->search([], 99, 2);
        $tooLow = $this->catalog->search([], -3, 2);

        self::assertSame(3, $second['pages']);
        self::assertCount(2, $second['rows']);
        self::assertSame(3, $tooFar['page']);
        self::assertCount(1, $tooFar['rows']);
        self::assertSame(1, $tooLow['page']);
        self::assertSame(['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1], $this->catalog->search(['search' => 'no-such-model'], 1, 2));
    }

    public function testTagHelpers(): void
    {
        $a = $this->addModel($this->owner);
        $b = $this->addModel($this->owner);
        $this->addTags($a, 'beta', 'alpha');
        $this->addTags($b, 'alpha');
        $this->exec("INSERT INTO tags (name) VALUES ('unused')");

        self::assertSame([], $this->catalog->tagsFor([]));
        self::assertSame([$a => ['alpha', 'beta'], $b => ['alpha']], $this->catalog->tagsFor([$a, $b]));
        self::assertSame(['alpha', 'beta'], $this->catalog->tagNames($a));
        self::assertSame([], $this->catalog->tagNames(999));
        self::assertSame(['alpha', 'beta'], $this->catalog->allTags(), 'tags without models are not offered as filters');
    }

    public function testSyncTagsReplacesTheSet(): void
    {
        $id = $this->addModel($this->owner);
        $this->addTags($id, 'old', 'keep');

        $this->catalog->syncTags($id, ['keep', 'fresh']);

        self::assertSame(['fresh', 'keep'], $this->catalog->tagNames($id));
        $this->catalog->syncTags($id, []);
        self::assertSame([], $this->catalog->tagNames($id));
    }

    public function testSuggestedRanksSharedTagsFirstAndSkipsHiddenAndSelf(): void
    {
        $base = $this->addModel($this->owner, ['title' => 'Base']);
        $related = $this->addModel($this->owner, ['title' => 'Related', 'view_count' => 1]);
        $popular = $this->addModel($this->owner, ['title' => 'Popular', 'view_count' => 900]);
        $this->addModel($this->owner, ['title' => 'Hidden', 'is_public' => 0]);
        $this->addTags($base, 'car');
        $this->addTags($related, 'car');

        $suggested = $this->catalog->suggested($base, 5);

        self::assertSame([$related, $popular], array_map(static fn (array $row): int => (int) $row['id'], $suggested));
    }

    public function testCreateUpdateAndVisibility(): void
    {
        $id = $this->catalog->create([
            'user_id' => $this->owner, 'title' => 'T', 'filename' => 'f.glb', 'thumb' => '',
            'description' => 'd', 'license' => 'CC0', 'price' => 5.5,
        ]);
        $this->catalog->update($id, ['title' => 'T2', 'description' => 'd2', 'filename' => 'g.glb', 'thumb' => 't.png', 'license' => 'CC BY', 'price' => 0]);
        $this->catalog->setConversions($id, 'a.gltf', 'a.glb', null, 'a.obj');
        $this->catalog->setVisibility($id, false);
        $this->catalog->incrementViews($id);
        $this->catalog->incrementViews($id);

        $model = $this->catalog->find($id);

        self::assertSame('T2', $model['title']);
        self::assertSame('g.glb', $model['filename']);
        self::assertSame('a.gltf', $model['file_gltf']);
        self::assertNull($model['file_usdz']);
        self::assertSame(0, (int) $model['is_public']);
        self::assertSame(2, (int) $model['view_count']);
        $this->catalog->setVisibility($id, true);
        self::assertSame(1, (int) $this->catalog->find($id)['is_public']);
    }

    public function testOwnedSavedAndLikedListsRespectVisibility(): void
    {
        $other = $this->addUser('other');
        $mine = $this->addModel($this->owner, ['title' => 'Mine']);
        $hiddenMine = $this->addModel($this->owner, ['title' => 'Hidden mine', 'is_public' => 0]);
        $theirs = $this->addModel($other, ['title' => 'Theirs']);
        $theirHidden = $this->addModel($other, ['title' => 'Their hidden', 'is_public' => 0]);
        foreach ([$mine, $hiddenMine, $theirs, $theirHidden] as $modelId) {
            $this->exec('INSERT INTO collections (user_id, model_id) VALUES (?, ?)', $this->owner, $modelId);
            $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $this->owner, $modelId);
        }

        $ids = static fn (array $rows): array => array_map(static fn (array $row): int => (int) $row['id'], $rows);

        self::assertEqualsCanonicalizing([$mine, $hiddenMine], $ids($this->catalog->ownedBy($this->owner)));
        self::assertEqualsCanonicalizing([$mine, $hiddenMine, $theirs], $ids($this->catalog->savedBy($this->owner)));
        self::assertEqualsCanonicalizing([$mine, $hiddenMine, $theirs], $ids($this->catalog->likedBy($this->owner)));
    }

    public function testSalesGuardAndDelete(): void
    {
        $id = $this->addModel($this->owner);
        self::assertFalse($this->catalog->hasSales($id));
        $this->exec("INSERT INTO orders (order_ref, buyer_id) VALUES ('R', ?)", $this->owner);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id) VALUES (1, ?, ?)', $id, $this->owner);
        self::assertTrue($this->catalog->hasSales($id));

        $other = $this->addModel($this->owner);
        $this->addTags($other, 'x');
        $this->catalog->delete($other);

        self::assertNull($this->catalog->find($other));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM model_tags'));
        self::assertSame(1, $this->catalog->count());
        self::assertCount(1, $this->catalog->adminList());
        self::assertSame('owner', $this->catalog->adminList()[0]['uploader']);
    }
}
