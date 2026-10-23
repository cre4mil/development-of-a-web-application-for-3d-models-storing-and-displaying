<?php

declare(strict_types=1);

/** Application bootstrap, required by every entry script in frontend/. */

require_once __DIR__ . '/support/Config.php';
require_once __DIR__ . '/support/Bootstrap.php';

// เริ่มระบบ: โหลดคลาสอัตโนมัติ อ่าน .env และเปิด session (ทุกไฟล์ใน frontend/ เรียกไฟล์นี้ก่อน)
App\Support\Bootstrap::init(dirname(__DIR__));
