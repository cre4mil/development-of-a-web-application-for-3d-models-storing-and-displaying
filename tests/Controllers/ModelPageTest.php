<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\ModelController;
use App\Services\ModelConverter;
use Tests\Support\AppTestCase;

/** Model detail page, embed view and downloads. */
final class ModelPageTest extends AppTestCase
{
    private ModelController $controller;
    private int $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->addUser('Owner');
        $this->controller = new ModelController($this->pdo, $this->files(), new ModelConverter($this->files(), '', ''));
    }

    private function paidModel(float $price = 20.0): int
    {
        return $this->addModel($this->owner, ['title' => 'Paid model', 'price' => $price]);
    }

    private function order(int $buyer, int $model, string $status): void
    {
        $this->exec('INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status) VALUES (?, ?, 20, ?)', 'R' . random_int(1000, 9999), $buyer, $status);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (?, ?, ?, 20)', (int) $this->pdo->lastInsertId(), $model, $this->owner);
    }

    public function testPublicModelPageRendersViewerAndMetadata(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Chair', 'description' => 'A nice chair', 'license' => 'CC BY', 'thumb' => 'c.png']);
        $this->addTags($id, 'furniture');
        $this->exec('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)', $id, $this->owner, 'hi');
        $other = $this->addModel($this->owner, ['title' => 'Suggested one', 'price' => 5]);

        $response = $this->controller->show($this->request(['id' => (string) $id]));
        $html = $response->body();

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<title>Chair – 3D Gallery</title>', $html);
        self::assertStringContainsString('data-viewer', $html);
        self::assertStringContainsString('A nice chair', $html);
        self::assertStringContainsString('#furniture', $html);
        self::assertStringContainsString('Creative Commons Attribution 4.0', $html);
        self::assertStringContainsString('creativecommons.org/licenses/by/4.0', $html);
        self::assertStringContainsString('Suggested one', $html);
        self::assertStringContainsString('฿5.00', $html);
        self::assertStringContainsString('Download 3D Model', $html);
        self::assertStringContainsString('data-bs-target="#downloadModal"', $html);
        self::assertStringContainsString('เข้าสู่ระบบ</a> เพื่อแสดงความคิดเห็น', $html);
        self::assertStringNotContainsString('data-action="edit-model"', $html);
        self::assertNotSame(0, $other);
    }

    public function testViewsAreCountedOncePerSessionAndNeverForTheOwner(): void
    {
        $id = $this->addModel($this->owner);

        $this->controller->show($this->request(['id' => (string) $id]));
        $this->controller->show($this->request(['id' => (string) $id]));
        self::assertSame(1, (int) $this->scalar('SELECT view_count FROM models WHERE id = ?', $id));

        $this->loginAs($this->owner, 'Owner');
        $this->controller->show($this->request(['id' => (string) $id]));
        self::assertSame(1, (int) $this->scalar('SELECT view_count FROM models WHERE id = ?', $id));

        $this->session = [];
        $this->controller->show($this->request(['id' => (string) $id]));
        self::assertSame(2, (int) $this->scalar('SELECT view_count FROM models WHERE id = ?', $id));
    }

    public function testMissingHiddenAndInvalidModelsAreNotFound(): void
    {
        $hidden = $this->addModel($this->owner, ['is_public' => 0]);

        self::assertSame(404, $this->controller->show($this->request(['id' => '999']))->status());
        self::assertSame(404, $this->controller->show($this->request(['id' => 'abc']))->status());
        self::assertSame(404, $this->controller->show($this->request())->status());
        self::assertSame(404, $this->controller->show($this->request(['id' => (string) $hidden]))->status());
    }

    public function testHiddenModelsAreVisibleToTheirOwnerAndAdmins(): void
    {
        $hidden = $this->addModel($this->owner, ['is_public' => 0, 'title' => 'Hidden one']);
        $admin = $this->addUser('Admin', true);

        $this->loginAs($this->owner, 'Owner');
        $asOwner = $this->controller->show($this->request(['id' => (string) $hidden]));
        self::assertSame(200, $asOwner->status());
        self::assertStringContainsString('ซ่อนจากสาธารณะ', $asOwner->body());
        self::assertStringContainsString('data-action="edit-model"', $asOwner->body());
        self::assertStringNotContainsString('data-action="follow"', $asOwner->body());

        $this->loginAs($admin, 'Admin', true);
        self::assertSame(200, $this->controller->show($this->request(['id' => (string) $hidden]))->status());
    }

    public function testSignedInVisitorSeesLikeSaveFollowAndCommentForm(): void
    {
        $viewer = $this->addUser('Viewer');
        $id = $this->addModel($this->owner);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $viewer, $id);
        $this->exec('INSERT INTO collections (user_id, model_id) VALUES (?, ?)', $viewer, $id);
        $this->exec('INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)', $viewer, $this->owner);
        $this->loginAs($viewer, 'Viewer');

        $html = $this->controller->show($this->request(['id' => (string) $id]))->body();

        self::assertStringContainsString('data-action="like" data-id="' . $id . '" data-liked="1"', $html);
        self::assertStringContainsString('data-saved="1"', $html);
        self::assertStringContainsString('data-following="1"', $html);
        self::assertStringContainsString('กำลังติดตาม', $html);
        self::assertStringContainsString('id="commentForm"', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('purchaseStates')]
    public function testPaidModelCallToAction(?string $orderStatus, bool $loggedIn, string $expected, string $unexpected): void
    {
        $id = $this->paidModel();
        $buyer = $this->addUser('Buyer');
        if ($orderStatus !== null) {
            $this->order($buyer, $id, $orderStatus);
        }
        if ($loggedIn) {
            $this->loginAs($buyer, 'Buyer');
        }

        $html = $this->controller->show($this->request(['id' => (string) $id]))->body();

        self::assertStringContainsString($expected, $html);
        self::assertStringNotContainsString($unexpected, $html);
    }

    /** @return array<string, array{string|null, bool, string, string}> */
    public static function purchaseStates(): array
    {
        return [
            'guest sees buy button that opens login' => [null, false, 'data-bs-target="#loginModal"><i class="bi bi-cart2', 'id="paymentModal"'],
            'signed-in user gets the payment dialog' => [null, true, 'data-bs-target="#paymentModal"', 'Download 3D Model'],
            'pending order waits for review' => ['pending', true, 'รอตรวจสอบการชำระเงิน', 'Buy Model'],
            'rejected order can buy again' => ['rejected', true, 'Buy Model', 'รอตรวจสอบการชำระเงิน'],
            'approved order unlocks download' => ['approved', true, 'Download 3D Model', 'Buy Model'],
        ];
    }

    public function testOwnerAndAdminCanDownloadPaidModelsWithoutBuying(): void
    {
        $id = $this->paidModel();
        $admin = $this->addUser('Admin', true);

        $this->loginAs($this->owner, 'Owner');
        self::assertStringContainsString('Download 3D Model', $this->controller->show($this->request(['id' => (string) $id]))->body());

        $this->loginAs($admin, 'Admin', true);
        self::assertStringContainsString('Download 3D Model', $this->controller->show($this->request(['id' => (string) $id]))->body());
    }

    public function testDownloadDialogListsAvailableFormats(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'orig.obj', 'file_glb' => 'conv.glb', 'file_obj' => 'orig.obj']);
        file_put_contents($this->uploads . 'conv.glb', 'glb-data');

        $html = $this->controller->show($this->request(['id' => (string) $id]))->body();

        self::assertStringContainsString('format=original', $html);
        self::assertStringContainsString('format=glb', $html);
        self::assertStringNotContainsString('format=obj', $html, 'the original is not listed twice');
    }

    public function testDownloadDialogWithoutFilesExplainsWhy(): void
    {
        $id = $this->addModel($this->owner, [], false);

        self::assertStringContainsString('ไม่พบไฟล์สำหรับดาวน์โหลด', $this->controller->show($this->request(['id' => (string) $id]))->body());
    }

    public function testViewerPrefersTheConvertedGlb(): void
    {
        $id = $this->addModel($this->owner, ['filename' => 'orig.obj', 'file_glb' => 'conv.glb']);
        file_put_contents($this->uploads . 'conv.glb', 'x');

        $html = $this->controller->show($this->request(['id' => (string) $id]))->body();

        self::assertStringContainsString('data-src="uploads/conv.glb" data-ext="glb"', $html);
    }

    public function testEmbedModeRendersOnlyTheViewer(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Embedded']);

        $response = $this->controller->show($this->request(['id' => (string) $id, 'embed' => '1']));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('viewer-embed', $response->body());
        self::assertStringContainsString('Embedded', $response->body());
        self::assertStringNotContainsString('site-header', $response->body());
    }

    public function testEmbedRespectsVisibility(): void
    {
        $hidden = $this->addModel($this->owner, ['is_public' => 0]);

        self::assertSame(404, $this->controller->show($this->request(['id' => (string) $hidden, 'embed' => '1']))->status());
    }

    // ── Downloads ────────────────────────────────────────────────────

    public function testFreeModelDownloadsWithAFriendlyName(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'Old Telephone', 'filename' => 'abc.glb']);

        $response = $this->controller->download($this->request(['id' => (string) $id]));

        self::assertSame(200, $response->status());
        self::assertSame('attachment; filename="Old_Telephone.glb"', $response->header('Content-Disposition'));
    }

    public function testDownloadPicksTheRequestedFormatAndFallsBackToTheOriginal(): void
    {
        $id = $this->addModel($this->owner, ['title' => 'M', 'filename' => 'a.glb', 'file_obj' => 'a_converted.obj', 'file_usdz' => null]);
        file_put_contents($this->uploads . 'a_converted.obj', 'obj');

        $obj = $this->controller->download($this->request(['id' => (string) $id, 'format' => 'obj']));
        $missing = $this->controller->download($this->request(['id' => (string) $id, 'format' => 'usdz']));
        $unknown = $this->controller->download($this->request(['id' => (string) $id, 'format' => 'zip']));

        self::assertSame('attachment; filename="M.obj"', $obj->header('Content-Disposition'));
        self::assertSame('attachment; filename="M.glb"', $missing->header('Content-Disposition'));
        self::assertSame('attachment; filename="M.glb"', $unknown->header('Content-Disposition'));
    }

    public function testDownloadOfMissingOrHiddenModelsFails(): void
    {
        $hidden = $this->addModel($this->owner, ['is_public' => 0]);
        $noFile = $this->addModel($this->owner, [], false);

        self::assertSame(404, $this->controller->download($this->request(['id' => '404']))->status());
        self::assertSame(404, $this->controller->download($this->request(['id' => (string) $hidden]))->status());
        $missingFile = $this->controller->download($this->request(['id' => (string) $noFile]));
        self::assertSame(404, $missingFile->status());
        self::assertStringContainsString('ไม่พบไฟล์บนเซิร์ฟเวอร์', $missingFile->body());
    }

    public function testPaidDownloadRequiresLoginAndAnApprovedOrder(): void
    {
        $id = $this->paidModel();
        $buyer = $this->addUser('Buyer');

        $guest = $this->controller->download($this->request(['id' => (string) $id]));
        self::assertSame(403, $guest->status());
        self::assertStringContainsString('คุณยังไม่ได้เข้าสู่ระบบ', $guest->body());

        $this->loginAs($buyer, 'Buyer');
        $unpaid = $this->controller->download($this->request(['id' => (string) $id]));
        self::assertSame(403, $unpaid->status());
        self::assertStringContainsString('ไม่สามารถดาวน์โหลดได้', $unpaid->body());

        $this->order($buyer, $id, 'pending');
        self::assertSame(403, $this->controller->download($this->request(['id' => (string) $id]))->status());

        $this->order($buyer, $id, 'approved');
        self::assertSame(200, $this->controller->download($this->request(['id' => (string) $id]))->status());
    }

    public function testOwnerAndAdminDownloadPaidModelsFreely(): void
    {
        $id = $this->paidModel();
        $admin = $this->addUser('Admin', true);

        $this->loginAs($this->owner, 'Owner');
        self::assertSame(200, $this->controller->download($this->request(['id' => (string) $id]))->status());

        $this->loginAs($admin, 'Admin', true);
        self::assertSame(200, $this->controller->download($this->request(['id' => (string) $id]))->status());
    }
}
