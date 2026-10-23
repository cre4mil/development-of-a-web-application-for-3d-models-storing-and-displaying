<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าแดชบอร์ดแอดมิน → AdminController::dashboard()
App\Http\Kernel::run(App\Controllers\AdminController::class, 'dashboard');
