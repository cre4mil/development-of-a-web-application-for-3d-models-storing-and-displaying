<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าคำสั่งซื้อของฉัน → OrderController::index()
App\Http\Kernel::run(App\Controllers\OrderController::class, 'index');
