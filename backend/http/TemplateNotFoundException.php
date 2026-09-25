<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** A view referenced a template file that does not exist. */
final class TemplateNotFoundException extends RuntimeException
{
}
