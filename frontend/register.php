<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// รับฟอร์มสมัครสมาชิก → AuthController::register()
App\Http\Kernel::run(App\Controllers\AuthController::class, 'register');
