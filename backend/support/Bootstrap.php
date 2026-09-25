<?php

declare(strict_types=1);

namespace App\Support;

/** One-time process setup: autoloading, .env and the PHP session. */
final class Bootstrap
{
    private const NAMESPACES = [
        'App\\Controllers\\' => 'controllers',
        'App\\Http\\' => 'http',
        'App\\Repositories\\' => 'repositories',
        'App\\Services\\' => 'services',
        'App\\Support\\' => 'support',
    ];

    public static function init(string $root): void
    {
        if (is_file($root . '/vendor/autoload.php')) {
            require_once $root . '/vendor/autoload.php';
        } else {
            // Keeps the site running on hosts where `composer install` was never executed.
            spl_autoload_register(self::autoloader($root));
            require_once $root . '/backend/helpers.php';
        }
        Config::loadEnvFile($root . '/.env');
        self::startSession(PHP_SAPI === 'cli', $_SERVER);
    }

    /** @return callable(string): void */
    public static function autoloader(string $root): callable
    {
        return static function (string $class) use ($root): void {
            foreach (self::NAMESPACES as $prefix => $dir) {
                $file = $root . '/backend/' . $dir . '/' . substr($class, strlen($prefix)) . '.php';
                if (str_starts_with($class, $prefix) && is_file($file)) {
                    require_once $file;
                }
            }
        };
    }

    /** @param array<string, mixed> $server */
    public static function startSession(bool $isCli, array $server): void
    {
        if ($isCli || session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $isHttps = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
            || (string) ($server['SERVER_PORT'] ?? '') === '443';
        session_set_cookie_params([ // NOSONAR -- the Secure flag follows the request scheme so local HTTP development keeps working.
            'httponly' => true,
            'secure' => $isHttps, // NOSONAR -- Secure cookies are enabled over HTTPS; localhost HTTP needs a non-secure cookie.
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_name('MODELSESSID');
        session_start();
    }
}
