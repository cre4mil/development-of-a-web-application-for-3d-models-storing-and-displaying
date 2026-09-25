<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\AccountRepository;
use Tests\Support\AppTestCase;

final class AccountRepositoryTest extends AppTestCase
{
    private AccountRepository $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new AccountRepository($this->pdo);
    }

    public function testUserLookupAndCreation(): void
    {
        $id = $this->accounts->create('Carol', 'carol@gmail.com', 'hash');

        self::assertSame($id, (int) $this->accounts->findByEmail('carol@gmail.com')['id']);
        self::assertNull($this->accounts->findByEmail('nobody@gmail.com'));
        self::assertSame('Carol', $this->accounts->find($id)['username']);
        self::assertNull($this->accounts->find(999));
        self::assertSame(1, $this->accounts->count());
        self::assertCount(1, $this->accounts->all());
    }

    public function testFinancialHistoryBlocksDeletion(): void
    {
        $clean = $this->addUser('clean');
        $seller = $this->addUser('seller');
        $buyer = $this->addUser('buyer');
        $payee = $this->addUser('payee');
        $model = $this->addModel($seller);
        $this->exec("INSERT INTO orders (order_ref, buyer_id) VALUES ('R1', ?)", $buyer);
        $this->exec('INSERT INTO order_items (order_id, model_id, creator_id) VALUES (1, ?, ?)', $model, $seller);
        $this->exec("INSERT INTO payout_requests (creator_id, amount) VALUES (?, 10)", $payee);

        self::assertFalse($this->accounts->hasFinancialHistory($clean));
        self::assertTrue($this->accounts->hasFinancialHistory($seller), 'as creator');
        self::assertTrue($this->accounts->hasFinancialHistory($buyer), 'as buyer');
        self::assertTrue($this->accounts->hasFinancialHistory($payee), 'payout');
    }

    public function testDeleteRemovesUserAndTheirInteractions(): void
    {
        $user = $this->addUser('leaving');
        $other = $this->addUser('staying');
        $model = $this->addModel($other);
        $this->exec('INSERT INTO likes (user_id, model_id) VALUES (?, ?)', $user, $model);
        $this->exec('INSERT INTO collections (user_id, model_id) VALUES (?, ?)', $user, $model);
        $this->exec('INSERT INTO comments (model_id, user_id, body) VALUES (?, ?, ?)', $model, $user, 'x');
        $this->exec('INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)', $user, $other);
        $this->exec('INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)', $other, $user);
        $this->exec('INSERT INTO creator_wallets (creator_id) VALUES (?)', $user);

        $this->accounts->delete($user);

        self::assertNull($this->accounts->find($user));
        foreach (['likes', 'collections', 'comments', 'follows', 'creator_wallets'] as $table) {
            self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM {$table}"), $table);
        }
        self::assertNotNull($this->accounts->find($other));
    }

    public function testModelsOfListsFileColumns(): void
    {
        $user = $this->addUser('maker');
        $this->addModel($user, ['filename' => 'one.glb', 'thumb' => 'one.png']);

        $models = $this->accounts->modelsOf($user);

        self::assertCount(1, $models);
        self::assertSame('one.glb', $models[0]['filename']);
        self::assertSame([], $this->accounts->modelsOf(999));
    }

    public function testWalletDefaultsAndBankDetails(): void
    {
        $user = $this->addUser('creator');

        $empty = $this->accounts->wallet($user);
        self::assertSame(0, $empty['available_balance']);
        self::assertSame('', $empty['bank_name']);

        $this->accounts->saveBank($user, 'KBank', '123', 'Creator');
        self::assertSame('KBank', $this->accounts->wallet($user)['bank_name']);

        $this->accounts->saveBank($user, 'SCB', '456', 'Creator 2');
        $wallet = $this->accounts->wallet($user);
        self::assertSame('SCB', $wallet['bank_name']);
        self::assertSame('456', $wallet['bank_account_no']);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM creator_wallets'));
    }

    public function testPlatformFeeSetting(): void
    {
        $admin = $this->addUser('admin', true);

        self::assertSame(10.0, $this->accounts->feePercent(10));
        $this->accounts->saveFee(12.5, $admin);
        self::assertSame(12.5, $this->accounts->feePercent(10));
        $this->accounts->saveFee(8.0, $admin);
        self::assertSame(8.0, $this->accounts->feePercent(10));
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM platform_settings'));
    }

    public function testWalletAndPayoutListings(): void
    {
        $rich = $this->addUser('rich');
        $poor = $this->addUser('poor');
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 500)', $rich);
        $this->exec('INSERT INTO creator_wallets (creator_id, available_balance) VALUES (?, 5)', $poor);
        $this->exec("INSERT INTO payout_requests (creator_id, amount, status) VALUES (?, 100, 'pending')", $rich);
        $this->exec("INSERT INTO payout_requests (creator_id, amount, status) VALUES (?, 40, 'transferred')", $rich);

        self::assertSame(['rich', 'poor'], array_column($this->accounts->wallets(), 'username'));
        self::assertCount(2, $this->accounts->payouts(10));
        self::assertCount(1, $this->accounts->payouts(1));
        self::assertSame(['pending' => 1, 'paid' => 40.0, 'ready' => 1], $this->accounts->payoutStats(300));
        self::assertSame(100.0, (float) $this->accounts->payout(1)['amount']);
        self::assertNull($this->accounts->payout(99));
    }
}
