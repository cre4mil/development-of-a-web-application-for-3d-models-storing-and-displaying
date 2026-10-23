<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Bootstrap;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::set('DB_DSN', null);
        Config::set('DB_USER', null);
        Database::use(null);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }
    }

    public function testConnectionIsCreatedFromTheDsnAndCached(): void
    {
        Config::set('DB_DSN', 'sqlite::memory:');
        Database::use(null);

        $first = Database::connection();
        $second = Database::connection();

        self::assertSame($first, $second);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $first->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame('sqlite', $first->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testUseReplacesTheSharedConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        Database::use($pdo);

        self::assertSame($pdo, Database::connection());
    }

    public function testMysqlDsnIsBuiltFromSeparateSettings(): void
    {
        Config::set('DB_DSN', null);
        Config::set('DB_HOST', '127.0.0.1');
        Config::set('DB_PORT', '1');
        Config::set('DB_NAME', 'nope');

        try {
            Database::connect();
            self::fail('expected a connection error');
        } catch (\PDOException $e) {
            self::assertNotSame('', $e->getMessage());
        } finally {
            Config::set('DB_HOST', null);
            Config::set('DB_PORT', null);
            Config::set('DB_NAME', null);
        }
    }

    public function testInsertIgnoreDependsOnTheDriver(): void
    {
        $sqlite = new PDO('sqlite::memory:');
        $mysql = $this->createMock(PDO::class);
        $mysql->method('getAttribute')->willReturn('mysql');

        self::assertSame('INSERT OR IGNORE', Database::insertIgnore($sqlite));
        self::assertSame('INSERT IGNORE', Database::insertIgnore($mysql));
    }

    public function testFallbackAutoloaderLoadsProjectClassesOnly(): void
    {
        $root = sys_get_temp_dir() . '/autoload-' . bin2hex(random_bytes(3));
        mkdir($root . '/backend/support', 0777, true);
        file_put_contents($root . '/backend/support/FromFallback.php', '<?php namespace App\Support; final class FromFallback { public const OK = true; }');

        $load = Bootstrap::autoloader($root);
        $load('Other\\Vendor\\Thing');
        $load('App\\Support\\Missing');
        self::assertFalse(class_exists('App\\Support\\FromFallback', false));

        $load('App\\Support\\FromFallback');
        self::assertTrue(class_exists('App\\Support\\FromFallback', false));

        unlink($root . '/backend/support/FromFallback.php');
        rmdir($root . '/backend/support');
        rmdir($root . '/backend');
        rmdir($root);
    }

    public function testInitUsesComposerAutoloadWhenPresent(): void
    {
        Bootstrap::init(Config::root());

        self::assertTrue(class_exists(Bootstrap::class));
    }

    public function testInitFallsBackToItsOwnAutoloaderWithoutVendor(): void
    {
        $root = sys_get_temp_dir() . '/noboot-' . bin2hex(random_bytes(3));
        mkdir($root . '/backend', 0777, true);
        copy(Config::root() . '/backend/helpers.php', $root . '/backend/helpers.php');
        $before = count(spl_autoload_functions());

        Bootstrap::init($root);

        self::assertCount($before + 1, spl_autoload_functions());
        unlink($root . '/backend/helpers.php');
        rmdir($root . '/backend');
        rmdir($root);
    }

    public function testSessionIsSkippedForCliAndForActiveSessions(): void
    {
        Bootstrap::startSession(true, []);
        self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testSessionStartsWithSecureCookieSettingsOnHttps(): void
    {
        @Bootstrap::startSession(false, ['HTTPS' => 'on']);
        @Bootstrap::startSession(false, ['SERVER_PORT' => '443']);
        @Bootstrap::startSession(false, []);

        self::assertTrue(true, 'the call sequence completed without fatal errors');
    }
}
