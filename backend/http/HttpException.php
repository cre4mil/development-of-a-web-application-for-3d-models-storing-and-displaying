<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Thrown by controllers to abort a JSON request with an error response. */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $error, string $message)
    {
        parent::__construct($message);
    }
}
