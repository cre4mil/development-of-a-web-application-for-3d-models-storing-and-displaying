<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Database;
use Throwable;

/** Entry point used by every script in frontend/. */
final class Kernel
{
    /** @param class-string $controller */
    public static function run(string $controller, string $method = 'handle'): void
    {
        self::dispatch($controller, $method)->send();
    }

    /** @param class-string $controller */
    public static function dispatch(string $controller, string $method = 'handle'): Response
    {
        try {
            return (new $controller(Database::connection()))->$method(Request::fromGlobals());
        } catch (Throwable $e) {
            error_log(sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            return Response::text('Server error. Please try again later.', 500);
        }
    }
}
