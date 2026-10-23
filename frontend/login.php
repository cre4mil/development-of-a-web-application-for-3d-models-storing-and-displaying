<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// รับฟอร์มล็อกอิน → AuthController::login()
App\Http\Kernel::run(App\Controllers\AuthController::class, 'login');
