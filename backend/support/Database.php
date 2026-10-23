<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

// สร้างและแชร์การเชื่อมต่อฐานข้อมูล (PDO) จากค่าใน .env
/** Lazily creates the shared PDO connection from environment configuration. */
final class Database
{
    private static ?PDO $connection = null;

    // คืนการเชื่อมต่อเดิม ถ้ายังไม่มีให้สร้างใหม่
    public static function connection(): PDO
    {
        return self::$connection ??= self::connect();
    }

    // เปลี่ยนการเชื่อมต่อ (ใช้ในเทสต์)
    /** Replaces the shared connection (tests) or resets it when null. */
    public static function use(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    // เชื่อมต่อ MySQL ด้วย DB_HOST/DB_NAME/DB_USER/DB_PASSWORD
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

    // คำสั่ง INSERT ที่ข้ามแถวซ้ำ ให้ตรงกับชนิด DB
    /** Portable "insert and skip duplicates" prefix for the active driver. */
    public static function insertIgnore(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    }
}
