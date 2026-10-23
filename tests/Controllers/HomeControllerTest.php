<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\HomeController;
use Tests\Support\AppTestCase;

final class HomeControllerTest extends AppTestCase
{
    private HomeController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new HomeController($this->pdo, $this->files());
    }

    public function testEmptyGalleryShowsAnEmptyState(): void
    {
        $response = $this->controller->index($this->request());

        self::assertSame(200, $response->status());
        self::assertStringContainsString('ไม่พบโมเดลที่ตรงกับเงื่อนไข', $response->body());
        self::assertStringContainsString('id="registerModal"', $response->body(), 'guests get the auth dialogs');
    }

    public function testGuestSeesPublicModelCardsWithPricesAndTags(): void
    {
        $owner = $this->addUser('Maker');
        $free = $this->addModel($owner, ['title' => 'Free chair', 'thumb' => 'chair.png']);
        $this->addModel($owner, ['title' => 'Paid table', 'price' => 49.5, 'view_count' => 1500]);
        $this->addModel($owner, ['title' => 'Secret', 'is_public' => 0]);
        $this->addTags($free, 'furniture');

        $html = $this->controller->index($this->request())->body();

        self::assertStringContainsString('Free chair', $html);
        self::assertStringContainsString('Paid table', $html);
        self::assertStringNotContainsString('Secret', $html);
        self::assertStringContainsString('฿49.50', $html);
        self::assertStringContainsString('>Free<', $html);
        self::assertStringContainsString('1.5k', $html);
        self::assertStringContainsString('#furniture', $html);
        self::assertStringContainsString('พบ <strong class="text-dark">2</strong> โมเดล', $html);
        self::assertStringNotContainsString('data-action="edit-model"', $html);
        self::assertStringNotContainsString('data-action="save"', $html);
    }

    public function testSignedInUserSeesOwnershipLikeAndSaveState(): void
    {
        $me = $this->addUser('Me');
        $other = $this->addUser('Other');
        $mine = $this->addModel($me, ['title' => 'Mine']);
        $liked = $this->addModel($other, ['title' => 'Liked']);
        $saved = $this->addModel($other, ['title' => 'Saved']);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $me, $liked);
        $this->exec('INSERT INTO collections (user_id, model_id) VALUES (?, ?)', $me, $saved);
        $this->loginAs($me, 'Me');

        $html = $this->controller->index($this->request(['sort' => 'views']))->body();

        self::assertStringContainsString('data-action="edit-model" data-id="' . $mine . '"', $html);
        self::assertStringContainsString('data-action="like" data-id="' . $liked . '" data-liked="1"', $html);
        self::assertStringContainsString('data-action="save" data-id="' . $saved . '" data-saved="1"', $html);
        self::assertStringNotContainsString('data-action="edit-model" data-id="' . $liked . '"', $html);
        self::assertStringContainsString('id="uploadModal"', $html);
    }

    public function testFiltersAreEchoedBackAndCanBeRemoved(): void
    {
        $owner = $this->addUser('Maker');
        $model = $this->addModel($owner, ['title' => 'Red car', 'license' => 'CC0']);
        $this->addTags($model, 'vehicle');

        $html = $this->controller->index($this->request([
            'search' => 'red', 'tag' => 'vehicle', 'license' => 'CC0', 'date' => 'week', 'sort' => 'likes',
        ]))->body();

        self::assertStringContainsString('value="red"', $html);
        self::assertStringContainsString('<option value="vehicle" selected>', $html);
        self::assertStringContainsString('<option value="CC0" selected>', $html);
        self::assertStringContainsString('<option value="week" selected>', $html);
        self::assertStringContainsString('<option value="likes" selected>', $html);
        self::assertStringContainsString('Red car', $html);
        self::assertStringContainsString('aria-label="ลบตัวกรอง"', $html);
    }

    public function testPaginationLinksKeepFilters(): void
    {
        $owner = $this->addUser('Maker');
        for ($i = 1; $i <= HomeController::PER_PAGE + 3; $i++) {
            $this->addModel($owner, ['title' => "Bulk {$i}", 'license' => 'CC0']);
        }

        $html = $this->controller->index($this->request(['license' => 'CC0', 'page' => '2']))->body();

        self::assertStringContainsString('หน้า 2/2', $html);
        self::assertStringContainsString('license=CC0&amp;page=1', $html);
        self::assertSame(3, substr_count($html, 'class="model-card"'));
    }

    public function testSearchLabelChangesSortDefault(): void
    {
        $html = $this->controller->index($this->request(['search' => 'anything']))->body();

        self::assertStringContainsString('ความเกี่ยวข้อง', $html);
    }
}
