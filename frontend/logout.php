<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// ออกจากระบบ → AuthController::logout()
App\Http\Kernel::run(App\Controllers\AuthController::class, 'logout');
