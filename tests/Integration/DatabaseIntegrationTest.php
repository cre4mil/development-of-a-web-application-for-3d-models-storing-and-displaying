<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\PdoOrderRepository;
use App\Repositories\PdoPayoutRepository;
use App\Services\OrderService;
use App\Services\PayoutService;
use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;

final class DatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, is_admin INTEGER NOT NULL DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE models (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, price REAL NOT NULL, is_public INTEGER NOT NULL)');
        $this->pdo->exec("CREATE TABLE platform_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL)");
        $this->pdo->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_ref TEXT NOT NULL UNIQUE, buyer_id INTEGER NOT NULL, total_amount REAL NOT NULL, payment_slip_url TEXT, payment_status TEXT NOT NULL, admin_note TEXT, admin_approved_by INTEGER, approved_at TEXT)");
        $this->pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, model_id INTEGER NOT NULL, creator_id INTEGER NOT NULL, price REAL NOT NULL, platform_fee REAL NOT NULL, creator_earning REAL NOT NULL)');
        $this->pdo->exec('CREATE TABLE creator_wallets (creator_id INTEGER PRIMARY KEY, available_balance REAL NOT NULL DEFAULT 0, total_earned REAL NOT NULL DEFAULT 0, pending_payout REAL NOT NULL DEFAULT 0)');
        $this->pdo->exec("CREATE TABLE payout_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, creator_id INTEGER NOT NULL, amount REAL NOT NULL, status TEXT NOT NULL, admin_transfer_slip TEXT, admin_note TEXT, transferred_at TEXT, admin_id INTEGER)");
        $this->pdo->exec('INSERT INTO users (id, is_admin) VALUES (1, 1), (2, 0), (3, 0)');
        $this->pdo->exec('INSERT INTO models (id, user_id, price, is_public) VALUES (10, 2, 100, 1), (11, 3, 50, 1), (12, 1, 20, 1)');
        $this->pdo->exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('platform_fee_pct', '10')");
    }

    public function testOrderCreationPersistsItemsAndApprovalCreditsCreators(): void
    {
        $service = new OrderService(new PdoOrderRepository($this->pdo));
        $order = $service->create(1, [10, 11, 12], 'uploads/slips/order.png', 'pending', null, 10);

        $this->assertSame(2, $order['items']);
        $this->assertSame(150.0, $order['total']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        $this->assertSame('ok', $service->process(1, 1, 'approved', 'verified'));
        $this->assertSame(90.0, (float) $this->pdo->query('SELECT available_balance FROM creator_wallets WHERE creator_id = 2')->fetchColumn());
        $this->assertSame(45.0, (float) $this->pdo->query('SELECT total_earned FROM creator_wallets WHERE creator_id = 3')->fetchColumn());
        $this->assertSame('already_processed', $service->process(1, 1, 'approved', 'again'));
        $this->assertSame(90.0, (float) $this->pdo->query('SELECT available_balance FROM creator_wallets WHERE creator_id = 2')->fetchColumn());
    }

    public function testPayoutPersistsStateAndReturnsFundsOnRejection(): void
    {
        $this->pdo->exec('INSERT INTO creator_wallets (creator_id, available_balance, total_earned, pending_payout) VALUES (2, 100, 100, 0)');
        $service = new PayoutService(new PdoPayoutRepository($this->pdo));

        $payoutId = $service->create(2, 40, 1);
        $this->assertSame(1, $payoutId);
        $this->assertSame(60.0, (float) $this->pdo->query('SELECT available_balance FROM creator_wallets WHERE creator_id = 2')->fetchColumn());
        $this->assertTrue($service->reject($payoutId, 'invalid bank account'));
        $this->assertSame(100.0, (float) $this->pdo->query('SELECT available_balance FROM creator_wallets WHERE creator_id = 2')->fetchColumn());
        $this->assertSame('rejected', $this->pdo->query('SELECT status FROM payout_requests WHERE id = 1')->fetchColumn());
    }

    public function testPayoutTransferPersistsSlipAndSettlesPendingBalance(): void
    {
        $this->pdo->exec('INSERT INTO creator_wallets (creator_id, available_balance, total_earned, pending_payout) VALUES (3, 75, 75, 0)');
        $service = new PayoutService(new PdoPayoutRepository($this->pdo));

        $payoutId = $service->create(3, 25, 1);
        $this->assertTrue($service->transfer($payoutId, 1, 'uploads/payout_slips/transfer.png'));
        $this->assertSame(50.0, (float) $this->pdo->query('SELECT available_balance FROM creator_wallets WHERE creator_id = 3')->fetchColumn());
        $this->assertSame(0.0, (float) $this->pdo->query('SELECT pending_payout FROM creator_wallets WHERE creator_id = 3')->fetchColumn());
        $this->assertSame('transferred', $this->pdo->query('SELECT status FROM payout_requests WHERE id = 1')->fetchColumn());
        $this->assertSame('uploads/payout_slips/transfer.png', $this->pdo->query('SELECT admin_transfer_slip FROM payout_requests WHERE id = 1')->fetchColumn());
    }

    public function testOrderRepositoryUsesDefaultFeeWhenNoSettingExists(): void
    {
        $this->pdo->exec('DELETE FROM platform_settings');
        $repository = new PdoOrderRepository($this->pdo);

        $this->assertSame(12.5, $repository->platformFeePercent(12.5));
        $this->assertFalse($repository->hasActiveOrder(1, 10));
        $this->assertTrue($repository->isAdmin(1));
        $this->assertFalse($repository->isAdmin(2));
    }

    public function testRepositoriesRollBackWhenTheirTransactionFails(): void
    {
        $orderRepository = new PdoOrderRepository($this->pdo);
        try {
            $orderRepository->transaction(function (): void {
                $this->pdo->exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('temporary', '1')");
                throw new RuntimeException('force rollback');
            });
            self::fail('Expected transaction failure');
        } catch (RuntimeException) {
            $this->assertFalse((bool) $this->pdo->query("SELECT COUNT(*) FROM platform_settings WHERE setting_key = 'temporary'")->fetchColumn());
        }

        $payoutRepository = new PdoPayoutRepository($this->pdo);
        try {
            $payoutRepository->transaction(function (): void {
                $this->pdo->exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('temporary-payout', '1')");
                throw new RuntimeException('force rollback');
            });
            self::fail('Expected transaction failure');
        } catch (RuntimeException) {
            $this->assertFalse((bool) $this->pdo->query("SELECT COUNT(*) FROM platform_settings WHERE setting_key = 'temporary-payout'")->fetchColumn());
        }
    }
}
