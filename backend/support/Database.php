<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/** Lazily creates the shared PDO connection from environment configuration. */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    /** Replaces the shared connection (tests) or resets it when null. */
    public static function use(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    public static function connect(): PDO
    {
        $dsn = Config::env('DB_DSN');
        if ($dsn === '') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::env('DB_HOST', '127.0.0.1'),
                Config::env('DB_PORT', '3306'),
                Config::env('DB_NAME', 'db_3dmodels')
            );
        }

        return new PDO($dsn, Config::env('DB_USER', 'root'), Config::env('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** Portable "insert and skip duplicates" prefix for the active driver. */
    public static function insertIgnore(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    }
}
