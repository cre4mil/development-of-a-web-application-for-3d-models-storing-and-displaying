<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
// API โมเดล (ดึง/อัปโหลด/แก้ไข/ลบ/ซ่อน) เรียกจาก core.js, upload.js → ModelController (ตอบกลับเป็น JSON)
App\Http\Kernel::run(App\Controllers\ModelController::class, 'handle');
