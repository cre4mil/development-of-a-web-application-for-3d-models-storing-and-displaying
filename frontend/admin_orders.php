<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าจัดการออเดอร์ (แอดมิน) → AdminController::orders()
App\Http\Kernel::run(App\Controllers\AdminController::class, 'orders');
