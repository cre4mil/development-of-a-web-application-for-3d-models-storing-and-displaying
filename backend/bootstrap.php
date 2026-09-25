<?php

declare(strict_types=1);

/** Application bootstrap, required by every entry script in frontend/. */

require_once __DIR__ . '/support/Config.php';
require_once __DIR__ . '/support/Bootstrap.php';

App\Support\Bootstrap::init(dirname(__DIR__));
