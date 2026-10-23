<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
// API ออเดอร์ (QR ชำระเงิน, สั่งซื้อ, อนุมัติ) เรียกจาก model.js, admin.js → OrderController (ตอบกลับเป็น JSON)
App\Http\Kernel::run(App\Controllers\OrderController::class, 'handle');
