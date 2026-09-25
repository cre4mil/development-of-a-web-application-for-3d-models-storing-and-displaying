<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\EarningsController;
use Tests\Support\AppTestCase;

final class EarningsControllerTest extends AppTestCase
{
    private EarningsController $controller;
    private int $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new EarningsController($this->pdo);
        $this->creator = $this->addUser('Creator');
    }

    public function testGuestsAreRedirected(): void
    {
        self::assertSame('index.php?login=1', $this->controller->index($this->request())->header('Location'));
        self::assertSame('index.php?login=1', $this->controller->saveBank($this->post())->header('Location'));
    }

    public function testNewCreatorSeesAnEmptyDashboard(): void
    {
        $this->loginAs($this->creator, 'Creator');

        $html = $this->controller->index($this->request())->body();

        self::assertStringContainsString('฿0.00', $html);
        self::assertStringContainsString('ยังไม่มีข้อมูลรายได้ที่อนุมัติแล้ว', $html);
        self::assertStringContainsString('ยังไม่มีประวัติการขาย', $html);
        self::assertStringContainsString('ยังไม่มีประวัติการรับเงิน', $html);
        self::assertStringContainsString('อีก ฿300.00', $html);
        self::assertStringNotContainsString('บันทึกข้อมูลธนาคารเรียบร้อยแล้ว', $html);
    }

    public function testDashboardShowsWalletSalesChartDataAndPayouts(): void
    {
        $buyer = $this->addUser('Buyer');
        $model = $this->addModel($this->creator, ['title' => 'Bestseller', 'price' => 100]);
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance, total_earned, pending_payout, bank_name, bank_account_no, bank_account_name) VALUES (?, 450, 900, 50, ?, ?, ?)', $this->creator, 'KBank', '123-4', 'Creator Name');
        $this->exec("INSERT INTO orders (order_ref, buyer_id, total_amount, payment_status) VALUES ('AAA111', ?, 100, 'approved'), ('BBB222', ?, 100, 'pending')", $buyer, $buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id, price, platform_fee, creator_earning) VALUES (1, ?, ?, 100, 10, 90), (2, ?, ?, 100, 10, 90)', $model, $this->creator, $model, $this->creator);
        $this->exec("INSERT INTO payout_requests (creator_id, amount, status, admin_transfer_slip, admin_note) VALUES (?, 100, 'transferred', 'uploads/payout_slips/s.png', 'paid'), (?, 20, 'rejected', NULL, NULL)", $this->creator, $this->creator);
        $this->exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('platform_fee_pct', '12.5')");
        $this->loginAs($this->creator, 'Creator');

        $html = $this->controller->index($this->request(['saved' => '1']))->body();

        self::assertStringContainsString('฿450.00', $html);
        self::assertStringContainsString('ยอดถึงเกณฑ์แล้ว', $html);
        self::assertStringContainsString('รอโอน ฿50.00', $html);
        self::assertStringContainsString('12.5%', $html);
        self::assertStringContainsString('Bestseller', $html);
        self::assertStringContainsString('AAA111', $html);
        self::assertStringContainsString('อนุมัติ', $html);
        self::assertStringContainsString('รอตรวจ', $html);
        self::assertStringContainsString('id="chartData"', $html);
        self::assertStringContainsString('uploads/payout_slips/s.png', $html);
        self::assertStringContainsString('โอนแล้ว', $html);
        self::assertStringContainsString('ปฏิเสธ', $html);
        self::assertStringContainsString('value="KBank"', $html);
        self::assertStringContainsString('บันทึกข้อมูลธนาคารเรียบร้อยแล้ว', $html);
    }

    public function testSavingBankDetails(): void
    {
        $this->loginAs($this->creator, 'Creator');

        $response = $this->controller->saveBank($this->post(['bank_name' => ' SCB ', 'bank_account_no' => '999', 'bank_account_name' => 'Me']));

        self::assertSame('creator_earnings.php?saved=1#bank', $response->header('Location'));
        $wallet = $this->pdo->query('SELECT * FROM creator_wallets')->fetch();
        self::assertSame('SCB', $wallet['bank_name']);
        self::assertSame($this->creator, (int) $wallet['creator_id']);
    }

    public function testPostingToTheDashboardRouteSavesTheForm(): void
    {
        $this->loginAs($this->creator, 'Creator');

        $response = $this->controller->index($this->post(['bank_name' => 'KTB']));

        self::assertSame('creator_earnings.php?saved=1#bank', $response->header('Location'));
    }

    public function testInvalidTokensAreRejectedWithoutWriting(): void
    {
        $this->loginAs($this->creator, 'Creator');
        $this->session['csrf'] = 'real';

        $response = $this->controller->saveBank($this->request([], ['csrf' => 'forged', 'bank_name' => 'KTB'], [], ['REQUEST_METHOD' => 'POST']));
        $get = $this->controller->saveBank($this->request());

        self::assertSame('creator_earnings.php', $response->header('Location'));
        self::assertSame('creator_earnings.php', $get->header('Location'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM creator_wallets'));
        self::assertNotEmpty($this->session['error']);
    }
}
