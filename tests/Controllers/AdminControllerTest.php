<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\AdminController;
use App\Repositories\PayoutStore;
use App\Services\PayoutService;
use RuntimeException;
use Tests\Support\AppTestCase;

final class AdminControllerTest extends AppTestCase
{
    private AdminController $controller;
    private int $admin;
    private int $seller;
    private int $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AdminController($this->pdo, $this->files());
        $this->admin = $this->addUser('Admin', true);
        $this->seller = $this->addUser('Seller');
        $this->buyer = $this->addUser('Buyer');
    }

    private function asAdmin(): void
    {
        $this->loginAs($this->admin, 'Admin', true);
    }

    private function act(string $action, array $post = [], array $files = []): array
    {
        $response = $this->controller->handle($this->post(['action' => $action] + $post, $files));

        return [$response->status(), $this->json($response)];
    }

    // ── Pages ────────────────────────────────────────────────────────

    public function testPagesAreAdminOnly(): void
    {
        foreach (['dashboard', 'orders', 'payouts'] as $page) {
            self::assertSame('index.php?login=1', $this->controller->{$page}($this->request())->header('Location'), "{$page} guest");
        }

        $this->loginAs($this->seller, 'Seller');
        foreach (['dashboard', 'orders', 'payouts'] as $page) {
            self::assertSame('index.php', $this->controller->{$page}($this->request())->header('Location'), "{$page} member");
        }
    }

    public function testDashboardSummarisesTheSystem(): void
    {
        $model = $this->addModel($this->seller, ['title' => 'Listed model', 'price' => 50]);
        $hidden = $this->addModel($this->seller, ['title' => 'Hidden model', 'is_public' => 0]);
        $this->exec("INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status, payment_slip_url) VALUES ('ORD-A', ?, 150, 'approved', 'uploads/slips/a.png'), ('ORD-B', ?, 50, 'pending', NULL)", $this->buyer, $this->buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (1, ?, ?, 100), (1, ?, ?, 50), (2, ?, ?, 50)', $model, $this->seller, $model, $this->seller, $model, $this->seller);
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 500)', $this->seller);
        $this->asAdmin();

        $html = $this->controller->dashboard($this->request())->body();

        self::assertStringContainsString('System Overview', $html);
        self::assertStringContainsString('ORD-A', $html);
        self::assertStringContainsString('฿150.00', $html);
        self::assertStringContainsString('+1', $html, 'multi-item orders show the extra count');
        self::assertStringContainsString('Listed model', $html);
        self::assertStringContainsString('Hidden model', $html);
        self::assertStringContainsString('data-admin-action="delete_user"', $html);
        self::assertStringContainsString('ดูสลิป', $html);
        self::assertStringContainsString('คุณ', $html);
        self::assertNotSame(0, $hidden);
    }

    public function testDashboardEmptyOrdersMessage(): void
    {
        $this->asAdmin();

        self::assertStringContainsString('ยังไม่มีรายการสั่งซื้อ', $this->controller->dashboard($this->request())->body());
    }

    public function testOrdersPageFiltersByStatus(): void
    {
        $model = $this->addModel($this->seller, ['price' => 10]);
        $this->exec("INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status, payment_slip_url, admin_note) VALUES ('PEND-1', ?, 10, 'pending', 'uploads/slips/p.png', NULL), ('DONE-1', ?, 10, 'approved', NULL, 'verified')", $this->buyer, $this->buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price) VALUES (1, ?, ?, 10), (2, ?, ?, 10)', $model, $this->seller, $model, $this->seller);
        $this->asAdmin();

        $all = $this->controller->orders($this->request())->body();
        $pending = $this->controller->orders($this->request(['status' => 'pending']))->body();

        self::assertStringContainsString('PEND-1', $all);
        self::assertStringContainsString('DONE-1', $all);
        self::assertStringContainsString('verified', $all);
        self::assertStringContainsString('data-admin-action="order-approve"', $all);
        self::assertStringContainsString('PEND-1', $pending);
        self::assertStringNotContainsString('DONE-1', $pending);
    }

    public function testOrdersPageEmptyState(): void
    {
        $this->asAdmin();

        self::assertStringContainsString('ไม่มี orders', $this->controller->orders($this->request())->body());
    }

    public function testPayoutsPageShowsWalletsAndRequests(): void
    {
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance, total_earned, pending_payout, bank_name, bank_account_no) VALUES (?, 500, 900, 100, ?, ?)', $this->seller, 'KBank', '111');
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 0)', $this->buyer);
        $this->exec("INSERT INTO payout_requests (creator_id, amount, status, admin_transfer_slip) VALUES (?, 100, 'pending', NULL), (?, 60, 'transferred', 'uploads/payout_slips/x.png')", $this->seller, $this->seller);
        $this->asAdmin();

        $html = $this->controller->payouts($this->request())->body();

        self::assertStringContainsString('Payout Management', $html);
        self::assertStringContainsString('พร้อมโอน', $html);
        self::assertStringContainsString('ยังไม่ถึงเกณฑ์', $html);
        self::assertStringContainsString('ยังไม่ระบุ', $html);
        self::assertStringContainsString('data-admin-action="payout-create"', $html);
        self::assertStringContainsString('data-admin-action="payout-transfer"', $html);
        self::assertStringContainsString('uploads/payout_slips/x.png', $html);
    }

    public function testPayoutsPageEmptyState(): void
    {
        $this->asAdmin();

        $html = $this->controller->payouts($this->request())->body();

        self::assertStringContainsString('ยังไม่มีข้อมูล Wallet', $html);
        self::assertStringContainsString('ยังไม่มีรายการ Payout', $html);
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function testActionsAreAdminOnly(): void
    {
        foreach (['delete_user', 'delete_model', 'create_payout', 'transfer_payout', 'reject_payout', 'update_fee'] as $action) {
            self::assertSame(401, $this->act($action)[0], "{$action} guest");
        }
        $this->loginAs($this->seller, 'Seller');
        self::assertSame(403, $this->act('delete_user', ['id' => (string) $this->buyer])[0]);
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM users WHERE username = 'Buyer'"));
    }

    // ── Users and models ─────────────────────────────────────────────

    public function testDeleteUserRemovesTheirModelsAndFiles(): void
    {
        $model = $this->addModel($this->seller, ['filename' => 'seller.glb']);
        $this->asAdmin();

        [$status, $data] = $this->act('delete_user', ['id' => (string) $this->seller]);

        self::assertSame(200, $status);
        self::assertTrue($data['ok']);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM users WHERE id = ?', $this->seller));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM models WHERE id = ?', $model));
        self::assertFileDoesNotExist($this->uploads . 'seller.glb');
    }

    public function testDeleteUserSafeguards(): void
    {
        $this->asAdmin();
        self::assertSame(422, $this->act('delete_user', ['id' => (string) $this->admin])[0]);
        self::assertSame(404, $this->act('delete_user', ['id' => '999'])[0]);

        $this->exec("INSERT INTO orders (order_ref, buyer_id) VALUES ('R', ?)", $this->buyer);
        [$status, $data] = $this->act('delete_user', ['id' => (string) $this->buyer]);
        self::assertSame(409, $status);
        self::assertSame('has_history', $data['error']);
    }

    public function testDeleteModel(): void
    {
        $free = $this->addModel($this->seller, ['filename' => 'free.glb']);
        $sold = $this->addModel($this->seller, ['price' => 10]);
        $this->exec("INSERT INTO orders (order_ref, buyer_id) VALUES ('R', ?)", $this->buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id) VALUES (1, ?, ?)', $sold, $this->seller);
        $this->asAdmin();

        self::assertTrue($this->act('delete_model', ['id' => (string) $free])[1]['ok']);
        self::assertFileDoesNotExist($this->uploads . 'free.glb');
        self::assertSame(404, $this->act('delete_model', ['id' => '999'])[0]);
        self::assertSame(409, $this->act('delete_model', ['id' => (string) $sold])[0]);
    }

    // ── Payouts ──────────────────────────────────────────────────────

    public function testCreatePayoutMovesMoneyToPending(): void
    {
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 500)', $this->seller);
        $this->asAdmin();

        [$status, $data] = $this->act('create_payout', ['creator_id' => (string) $this->seller, 'amount' => '200.50']);

        self::assertSame(200, $status);
        self::assertSame(1, $data['payout_id']);
        self::assertSame(299.5, (float) $this->scalar('SELECT available_balance FROM creator_wallets'));
        self::assertSame(200.5, (float) $this->scalar('SELECT pending_payout FROM creator_wallets'));
    }

    public function testCreatePayoutValidation(): void
    {
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 50)', $this->seller);
        $this->asAdmin();

        self::assertSame('bad_params', $this->act('create_payout', ['creator_id' => '0', 'amount' => '10'])[1]['error']);
        self::assertSame('bad_params', $this->act('create_payout', ['creator_id' => (string) $this->seller, 'amount' => '-5'])[1]['error']);
        [$status, $data] = $this->act('create_payout', ['creator_id' => (string) $this->seller, 'amount' => '500']);
        self::assertSame([422, 'insufficient_balance'], [$status, $data['error']]);
    }

    public function testDatabaseFailuresBecomeJsonErrorsAndTheSlipIsCleanedUp(): void
    {
        $store = $this->createMock(PayoutStore::class);
        $store->method('availableBalance')->willReturn(100.0);
        $store->method('transaction')->willThrowException(new RuntimeException('db down'));
        $store->method('findRequest')->willReturn(['status' => 'pending', 'creator_id' => 1, 'amount' => 10]);
        $controller = new AdminController($this->pdo, $this->files(), new PayoutService($store));
        $this->asAdmin();
        $log = tempnam(sys_get_temp_dir(), 'log');
        $previous = ini_set('error_log', $log);

        $create = $controller->handle($this->post(['action' => 'create_payout', 'creator_id' => '1', 'amount' => '10']));
        $transfer = $controller->handle($this->post(['action' => 'transfer_payout', 'payout_id' => '1'], ['slip' => $this->upload('s.png')]));
        $reject = $controller->handle($this->post(['action' => 'reject_payout', 'payout_id' => '1']));

        ini_set('error_log', (string) $previous);
        foreach ([$create, $transfer, $reject] as $response) {
            self::assertSame(500, $response->status());
            self::assertSame('server_error', $this->json($response)['error']);
        }
        self::assertSame([], glob($this->uploads . 'payout_slips/*'), 'the slip is removed when the transfer fails');
        self::assertStringContainsString('db down', (string) file_get_contents($log));
        unlink($log);
    }

    public function testTransferPayoutStoresTheSlip(): void
    {
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 500)', $this->seller);
        $this->asAdmin();
        $this->act('create_payout', ['creator_id' => (string) $this->seller, 'amount' => '200']);

        [$status, $data] = $this->act('transfer_payout', ['payout_id' => '1'], ['slip' => $this->upload('proof.png', 'img')]);

        self::assertSame(200, $status);
        self::assertTrue($data['ok']);
        $payout = $this->pdo->query('SELECT * FROM payout_requests')->fetch();
        self::assertSame('transferred', $payout['status']);
        self::assertMatchesRegularExpression('#^uploads/payout_slips/payout_slip_[0-9a-f]{16}\.png$#', $payout['admin_transfer_slip']);
        self::assertSame(0.0, (float) $this->scalar('SELECT pending_payout FROM creator_wallets'));
    }

    public function testTransferPayoutValidation(): void
    {
        $this->asAdmin();
        $slip = fn (string $name = 's.png') => ['slip' => $this->upload($name)];

        self::assertSame('bad_id', $this->act('transfer_payout', ['payout_id' => '0'], $slip())[1]['error']);
        self::assertSame('slip_required', $this->act('transfer_payout', ['payout_id' => '1'])[1]['error']);
        self::assertSame('slip_format', $this->act('transfer_payout', ['payout_id' => '1'], $slip('s.php'))[1]['error']);
        $big = $slip();
        $big['slip']['size'] = 9 * 1024 * 1024;
        self::assertSame('slip_too_large', $this->act('transfer_payout', ['payout_id' => '1'], $big)[1]['error']);
        self::assertSame(404, $this->act('transfer_payout', ['payout_id' => '77'], $slip())[0]);
        self::assertSame([], glob($this->uploads . 'payout_slips/*'));

        $failing = new AdminController($this->pdo, new \App\Services\ModelFiles($this->uploads, static fn (): bool => false));
        $response = $failing->handle($this->post(['action' => 'transfer_payout', 'payout_id' => '1'], $slip()));
        self::assertSame('slip_save_failed', $this->json($response)['error']);
    }

    public function testRejectPayoutReturnsTheMoney(): void
    {
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 500)', $this->seller);
        $this->asAdmin();
        $this->act('create_payout', ['creator_id' => (string) $this->seller, 'amount' => '200']);

        self::assertSame('bad_id', $this->act('reject_payout', ['payout_id' => '0'])[1]['error']);
        self::assertTrue($this->act('reject_payout', ['payout_id' => '1', 'note' => 'wrong account'])[1]['ok']);
        self::assertSame(500.0, (float) $this->scalar('SELECT available_balance FROM creator_wallets'));
        self::assertSame(404, $this->act('reject_payout', ['payout_id' => '1'])[0]);
    }

    // ── Fee ──────────────────────────────────────────────────────────

    public function testUpdateFee(): void
    {
        $this->asAdmin();

        self::assertSame(12.5, $this->act('update_fee', ['fee_pct' => '12.5'])[1]['fee_pct']);
        self::assertSame('12.5', $this->scalar("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_pct'"));
        self::assertSame('bad_value', $this->act('update_fee', ['fee_pct' => '101'])[1]['error']);
        self::assertSame('bad_value', $this->act('update_fee', [])[1]['error']);
        self::assertSame('bad_value', $this->act('update_fee', ['fee_pct' => '-1'])[1]['error']);
    }
}
