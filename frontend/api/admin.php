<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
// API แอดมิน (ลบผู้ใช้/โมเดล, จ่ายเงิน, ค่าธรรมเนียม) เรียกจาก admin.js → AdminController (ตอบกลับเป็น JSON)
App\Http\Kernel::run(App\Controllers\AdminController::class, 'handle');
