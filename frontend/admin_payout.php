<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าจ่ายเงินครีเอเตอร์ (แอดมิน) → AdminController::payouts()
App\Http\Kernel::run(App\Controllers\AdminController::class, 'payouts');
