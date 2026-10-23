<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\PdoOrderRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Production runs on MySQL while the suite runs on SQLite, so the MySQL-specific SQL is
 * verified here by checking the statement that would be sent to the server.
 */
final class MysqlDialectTest extends TestCase
{
    public function testWalletCreditUsesAnAtomicUpsertOnMysql(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([7, 12.5, 12.5]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->willReturn('mysql');
        $pdo->expects(self::once())->method('prepare')
            ->with(self::stringContains('ON DUPLICATE KEY UPDATE available_balance = available_balance + VALUES(available_balance)'))
            ->willReturn($statement);

        (new PdoOrderRepository($pdo))->creditWallet(7, 12.5);
    }
}
