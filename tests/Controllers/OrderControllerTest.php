<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\OrderController;
use App\Repositories\OrderStore;
use App\Services\OrderService;
use App\Services\SlipVerifier;
use App\Support\Config;
use RuntimeException;
use Tests\Support\AppTestCase;

final class OrderControllerTest extends AppTestCase
{
    private int $seller;
    private int $buyer;
    private int $admin;
    private int $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = $this->addUser('Seller');
        $this->buyer = $this->addUser('Buyer');
        $this->admin = $this->addUser('Admin', true);
        $this->model = $this->addModel($this->seller, ['title' => 'Paid', 'price' => 100]);
    }

    protected function tearDown(): void
    {
        Config::set('PAYMENT_METHOD', null);
        parent::tearDown();
    }

    private function controller(?SlipVerifier $verifier = null, ?OrderService $orders = null): OrderController
    {
        return new OrderController($this->pdo, $this->files(), $orders, $verifier ?? new SlipVerifier('', ''));
    }

    private function checkout(OrderController $controller, array $post = [], ?array $slip = null): array
    {
        $files = ['slip' => $slip ?? $this->upload('slip.png', 'png')];
        $response = $controller->handle($this->post($post + ['action' => 'create', 'model_ids' => [(string) $this->model]], $files));

        return [$response->status(), $this->json($response)];
    }

    // ── Buyer page ───────────────────────────────────────────────────

    public function testOrdersPageNeedsLogin(): void
    {
        $response = $this->controller()->index($this->request());

        self::assertSame('index.php?login=1', $response->header('Location'));
    }

    public function testOrdersPageListsOrdersWithDownloadLinksWhenApproved(): void
    {
        $this->loginAs($this->buyer, 'Buyer');
        $this->exec("INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status, admin_note) VALUES ('APPROVED1', ?, 100, 'approved', NULL), ('REJECTED1', ?, 100, 'rejected', 'blurry slip'), ('PENDING1', ?, 100, 'pending', NULL)", $this->buyer, $this->buyer, $this->buyer);
        foreach ([1, 2, 3] as $orderId) {
            $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (?, ?, ?, 100)', $orderId, $this->model, $this->seller);
        }

        $html = $this->controller()->index($this->request())->body();

        self::assertStringContainsString('APPROVED1', $html);
        self::assertStringContainsString('ปลดล็อกดาวน์โหลดแล้ว', $html);
        self::assertStringContainsString('blurry slip', $html);
        self::assertStringContainsString('รอตรวจสอบ', $html);
        self::assertSame(1, substr_count($html, 'download.php?id=' . $this->model), 'only the approved order links to the download');
    }

    public function testOrdersPageEmptyState(): void
    {
        $this->loginAs($this->buyer, 'Buyer');

        self::assertStringContainsString('ยังไม่มีประวัติการสั่งซื้อ', $this->controller()->index($this->request())->body());
    }

    // ── QR ───────────────────────────────────────────────────────────

    public function testQrReturnsAPromptPayPayload(): void
    {
        $response = $this->controller()->handle($this->request(['action' => 'qr', 'model_id' => (string) $this->model]));
        $data = $this->json($response);

        self::assertSame(200, $response->status());
        self::assertSame('promptpay', $data['method']);
        self::assertSame(100, $data['amount']);
        self::assertStringStartsWith('000201', $data['payload']);
        self::assertArrayNotHasKey('account', $data, 'the raw account is not disclosed for QR methods');
    }

    public function testQrForBankTransfersReturnsAccountDetails(): void
    {
        Config::set('PAYMENT_METHOD', 'kbank');

        $data = $this->json($this->controller()->handle($this->request(['action' => 'qr', 'model_id' => (string) $this->model])));

        self::assertSame('', $data['payload']);
        self::assertSame(Config::paymentAccount(), $data['account']);
        self::assertSame(Config::paymentName(), $data['name']);
    }

    public function testQrRejectsFreeAndUnknownModels(): void
    {
        $free = $this->addModel($this->seller, ['price' => 0]);

        self::assertSame(404, $this->controller()->handle($this->request(['action' => 'qr', 'model_id' => '999']))->status());
        self::assertSame(404, $this->controller()->handle($this->request(['action' => 'qr', 'model_id' => (string) $free]))->status());
    }

    // ── Checkout ─────────────────────────────────────────────────────

    public function testCheckoutNeedsPostLoginAndCsrf(): void
    {
        $controller = $this->controller();

        self::assertSame(405, $controller->handle($this->request(['action' => 'create']))->status());
        self::assertSame(401, $controller->handle($this->post(['action' => 'create']))->status());
    }

    public function testCheckoutValidatesItemsAndSlip(): void
    {
        $this->loginAs($this->buyer, 'Buyer');
        $controller = $this->controller();

        [$status, $data] = $this->checkout($controller, ['model_ids' => []]);
        self::assertSame([422, 'no_models'], [$status, $data['error']]);

        $response = $controller->handle($this->post(['action' => 'create', 'model_ids' => [(string) $this->model]]));
        self::assertSame('slip_required', $this->json($response)['error']);

        [, $data] = $this->checkout($controller, [], $this->upload('slip.php'));
        self::assertSame('slip_format', $data['error']);

        $big = $this->upload('slip.png');
        $big['size'] = 6 * 1024 * 1024;
        [, $data] = $this->checkout($controller, [], $big);
        self::assertSame('slip_too_large', $data['error']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM orders'));
    }

    public function testCheckoutRefusesOwnAndAlreadyPurchasedModels(): void
    {
        $controller = $this->controller();

        $this->loginAs($this->seller, 'Seller');
        [$status, $data] = $this->checkout($controller);
        self::assertSame([422, 'no_valid_items'], [$status, $data['error']]);

        $this->loginAs($this->buyer, 'Buyer');
        self::assertSame(200, $this->checkout($controller)[0]);
        [$status, $data] = $this->checkout($controller);
        self::assertSame([422, 'no_valid_items'], [$status, $data['error']], 'a pending order blocks a duplicate purchase');
    }

    public function testCheckoutCreatesAPendingOrderAndKeepsTheSlip(): void
    {
        $this->loginAs($this->buyer, 'Buyer');

        [$status, $data] = $this->checkout($this->controller());

        self::assertSame(200, $status);
        self::assertTrue($data['ok']);
        self::assertSame('pending', $data['status']);
        self::assertSame(1, $data['items']);
        self::assertSame(100, $data['total']);
        self::assertStringContainsString('รอผู้ดูแลระบบตรวจสอบ', $data['message']);
        $order = $this->pdo->query('SELECT * FROM orders')->fetch();
        self::assertSame('pending', $order['payment_status']);
        self::assertSame($data['order_ref'], $order['order_ref']);
        self::assertMatchesRegularExpression('#^uploads/slips/slip_[0-9a-f]{16}\.png$#', $order['payment_slip_url']);
        self::assertFileExists($this->uploads . substr($order['payment_slip_url'], strlen('uploads/')));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM creator_wallets'));
    }

    public function testVerifiedSlipsApproveTheOrderAndCreditTheCreator(): void
    {
        $this->loginAs($this->buyer, 'Buyer');
        $verifier = new SlipVerifier('KEY', '', static fn (): string => '{"success":true,"data":{"amount":100}}');

        [, $data] = $this->checkout($this->controller($verifier));

        self::assertSame('approved', $data['status']);
        self::assertStringContainsString('ดาวน์โหลดได้ทันที', $data['message']);
        self::assertSame(90.0, (float) $this->scalar('SELECT available_balance FROM creator_wallets WHERE creator_id = ?', $this->seller));
    }

    public function testFakeSlipsAreRejectedAndRemoved(): void
    {
        $this->loginAs($this->buyer, 'Buyer');
        $verifier = new SlipVerifier('KEY', '', static fn (): string => '{"success":false}');

        [$status, $data] = $this->checkout($this->controller($verifier));

        self::assertSame([422, 'slip_api_rejected'], [$status, $data['error']]);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM orders'));
        self::assertSame([], glob($this->uploads . 'slips/*'));
    }

    public function testCheckoutReportsStorageAndDatabaseFailures(): void
    {
        $this->loginAs($this->buyer, 'Buyer');

        $storageFails = new OrderController($this->pdo, new \App\Services\ModelFiles($this->uploads, static fn (): bool => false));
        [$status, $data] = $this->checkout($storageFails);
        self::assertSame([500, 'slip_save_failed'], [$status, $data['error']]);

        $store = $this->createMock(OrderStore::class);
        $store->method('purchasableModels')->willReturn([['id' => $this->model, 'user_id' => $this->seller, 'price' => 100]]);
        $store->method('platformFeePercent')->willReturn(10.0);
        $store->method('hasActiveOrder')->willReturn(false);
        $store->method('transaction')->willThrowException(new RuntimeException('db down'));
        [$status, $data] = $this->checkout($this->controller(null, new OrderService($store)));

        self::assertSame([500, 'order_failed'], [$status, $data['error']]);
        self::assertSame([], glob($this->uploads . 'slips/*'), 'the stored slip is cleaned up');
    }

    // ── Admin decision ───────────────────────────────────────────────

    private function pendingOrder(): int
    {
        $this->loginAs($this->buyer, 'Buyer');
        $this->checkout($this->controller());

        return (int) $this->scalar('SELECT id FROM orders');
    }

    public function testAdminApprovesAPendingOrder(): void
    {
        $orderId = $this->pendingOrder();
        $this->loginAs($this->admin, 'Admin', true);

        $response = $this->controller()->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'approved', 'note' => 'ok']));

        self::assertSame(['ok' => true, 'status' => 'approved'], $this->json($response));
        self::assertSame('approved', $this->scalar('SELECT payment_status FROM orders WHERE id = ?', $orderId));
        self::assertSame(90.0, (float) $this->scalar('SELECT available_balance FROM creator_wallets WHERE creator_id = ?', $this->seller));
    }

    public function testStatusChangeErrors(): void
    {
        $orderId = $this->pendingOrder();
        $controller = $this->controller();

        $this->session = [];
        self::assertSame(401, $controller->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'approved']))->status());
        $this->loginAs($this->buyer, 'Buyer');
        self::assertSame(403, $controller->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'approved']))->status());

        $this->loginAs($this->admin, 'Admin', true);
        self::assertSame(422, $controller->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'hacked']))->status());
        self::assertSame(404, $controller->handle($this->post(['action' => 'status', 'order_id' => '999', 'status' => 'approved']))->status());

        $controller->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'rejected']));
        $again = $controller->handle($this->post(['action' => 'status', 'order_id' => (string) $orderId, 'status' => 'approved']));
        self::assertSame(409, $again->status());
        self::assertSame('already_processed', $this->json($again)['error']);
    }

    public function testDefaultConstructionWiresRealCollaborators(): void
    {
        $controller = new OrderController($this->pdo);

        self::assertSame(404, $controller->handle($this->request(['action' => 'qr', 'model_id' => '999']))->status());
    }
}
