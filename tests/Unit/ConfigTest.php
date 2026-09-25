<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['APP_TEST_KEY', 'APP_TEST_A', 'APP_TEST_B', 'APP_TEST_C', 'PAYMENT_METHOD', 'PAYMENT_ACCOUNT', 'PAYMENT_NAME', 'BLENDER_PATH', 'UPLOAD_DIR'] as $key) {
            Config::set($key, null);
            putenv($key);
        }
    }

    public function testEnvFallsBackToDefaultForMissingOrEmptyValues(): void
    {
        self::assertSame('fallback', Config::env('APP_TEST_KEY', 'fallback'));
        putenv('APP_TEST_KEY=');
        self::assertSame('fallback', Config::env('APP_TEST_KEY', 'fallback'));
        putenv('APP_TEST_KEY=real');
        self::assertSame('real', Config::env('APP_TEST_KEY', 'fallback'));
    }

    public function testOverridesWinAndCanBeRemoved(): void
    {
        putenv('APP_TEST_KEY=env');
        Config::set('APP_TEST_KEY', 'override');
        self::assertSame('override', Config::env('APP_TEST_KEY'));
        Config::set('APP_TEST_KEY', null);
        self::assertSame('env', Config::env('APP_TEST_KEY'));
    }

    public function testLoadEnvFileParsesPairsWithoutOverridingRealVariables(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\n\nAPP_TEST_A=one\nAPP_TEST_B=\"two words\"\nnot a pair\nAPP_TEST_C=from-file\n");
        putenv('APP_TEST_C=from-process');

        Config::loadEnvFile($file);

        self::assertSame('one', Config::env('APP_TEST_A'));
        self::assertSame('two words', Config::env('APP_TEST_B'));
        self::assertSame('from-process', Config::env('APP_TEST_C'));
        unlink($file);
    }

    public function testLoadEnvFileIgnoresMissingFiles(): void
    {
        Config::loadEnvFile('/definitely/not/here.env');
        self::assertSame('', Config::env('APP_TEST_KEY'));
    }

    public function testDerivedSettings(): void
    {
        self::assertSame(dirname(__DIR__, 2), Config::root());
        self::assertStringEndsWith('/frontend/uploads/', str_replace('\\', '/', Config::uploadDir()));

        Config::set('UPLOAD_DIR', '/var/data/uploads\\');
        self::assertSame('/var/data/uploads/', Config::uploadDir());

        self::assertSame('promptpay', Config::paymentMethod());
        Config::set('PAYMENT_METHOD', 'kbank');
        Config::set('PAYMENT_ACCOUNT', '123');
        Config::set('PAYMENT_NAME', 'Shop');
        Config::set('BLENDER_PATH', '/opt/blender');
        self::assertSame('kbank', Config::paymentMethod());
        self::assertSame('123', Config::paymentAccount());
        self::assertSame('Shop', Config::paymentName());
        self::assertSame('/opt/blender', Config::blenderPath());
    }
}
