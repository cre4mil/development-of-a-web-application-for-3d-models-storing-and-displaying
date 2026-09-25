<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Environment-driven configuration.
 *
 * Values come from real environment variables first and from the project's
 * `.env` file second (see `.env.example`).
 */
final class Config
{
    public const PLATFORM_FEE_PCT = 10;
    public const PAYOUT_THRESHOLD = 300;

    /** @var array<string, string> */
    private static array $overrides = [];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function env(string $key, string $default = ''): string
    {
        if (isset(self::$overrides[$key])) {
            return self::$overrides[$key];
        }
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }

    /** Overrides a value for the current process (used by tests). */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null) {
            unset(self::$overrides[$key]);
            return;
        }
        self::$overrides[$key] = $value;
    }

    /** Loads KEY=VALUE pairs from a dotenv file without overriding real environment variables. */
    public static function loadEnvFile(string $path): void
    {
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (getenv($key) === false) {
                putenv($key . '=' . trim($value, "\"'"));
            }
        }
    }

    public static function uploadDir(): string
    {
        return rtrim(self::env('UPLOAD_DIR', self::root() . '/frontend/uploads'), "/\\") . '/';
    }

    public static function paymentMethod(): string
    {
        return self::env('PAYMENT_METHOD', 'promptpay');
    }

    public static function paymentAccount(): string
    {
        return self::env('PAYMENT_ACCOUNT', '0949491035');
    }

    public static function paymentName(): string
    {
        return self::env('PAYMENT_NAME', 'ปาณิสรา กุลคำ');
    }

    /** Absolute path to the Blender executable, or '' when conversion is disabled. */
    public static function blenderPath(): string
    {
        return self::env('BLENDER_PATH');
    }
}
