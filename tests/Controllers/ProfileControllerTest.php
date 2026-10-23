<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\ProfileController;
use Tests\Support\AppTestCase;

final class ProfileControllerTest extends AppTestCase
{
    private ProfileController $controller;
    private int $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProfileController($this->pdo, $this->files());
        $this->me = $this->addUser('Me');
    }

    public function testGuestsAreSentToTheLoginDialog(): void
    {
        foreach (['index', 'liked'] as $page) {
            $response = $this->controller->{$page}($this->request());
            self::assertSame(302, $response->status(), $page);
            self::assertSame('index.php?login=1', $response->header('Location'), $page);
        }
    }

    public function testProfileWithoutModelsShowsEmptyStates(): void
    {
        $this->loginAs($this->me, 'Me');

        $html = $this->controller->index($this->request())->body();

        self::assertStringContainsString('ยังไม่มีโมเดลที่คุณอัปโหลด', $html);
        self::assertStringContainsString('ยังไม่มีโมเดลที่บันทึกไว้', $html);
        self::assertStringContainsString('0 ผู้ติดตาม', $html);
    }

    public function testProfileListsOwnedSavedModelsAndTotals(): void
    {
        $other = $this->addUser('Other');
        $public = $this->addModel($this->me, ['title' => 'Public one', 'price' => 30, 'view_count' => 1200]);
        $this->addModel($this->me, ['title' => 'Hidden one', 'is_public' => 0]);
        $saved = $this->addModel($other, ['title' => 'Saved one']);
        $this->exec('INSERT INTO collections (user_id, model_id) VALUES (?, ?)', $this->me, $saved);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $other, $public);
        $this->exec('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)', $public, $other, 'hi');
        $this->exec('INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)', $other, $this->me);
        $this->loginAs($this->me, 'Me', true);

        $html = $this->controller->index($this->request())->body();

        self::assertStringContainsString('Public one', $html);
        self::assertStringContainsString('Hidden one', $html);
        self::assertStringContainsString('Saved one', $html);
        self::assertStringContainsString('฿30.00', $html);
        self::assertStringContainsString('แสดงบนหน้าหลัก', $html);
        self::assertStringContainsString('ซ่อน', $html);
        self::assertStringContainsString('1 ผู้ติดตาม', $html);
        self::assertStringContainsString('data-row="' . $public . '"', $html);
        self::assertStringContainsString('ผู้ดูแลระบบ', $html);
        self::assertMatchesRegularExpression('/class="num">1,200</', $html, 'total views');
    }

    public function testLikedPageListsLikedModels(): void
    {
        $other = $this->addUser('Other');
        $liked = $this->addModel($other, ['title' => 'Loved model']);
        $this->addModel($other, ['title' => 'Ignored model']);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $this->me, $liked);
        $this->loginAs($this->me, 'Me');

        $html = $this->controller->liked($this->request())->body();

        self::assertStringContainsString('Loved model', $html);
        self::assertStringNotContainsString('Ignored model', $html);
        self::assertStringContainsString('ทั้งหมด 1 รายการ', $html);
    }

    public function testLikedPageEmptyState(): void
    {
        $this->loginAs($this->me, 'Me');

        self::assertStringContainsString('ยังไม่มีโมเดลที่ถูกใจ', $this->controller->liked($this->request())->body());
    }
}
