<?php

require_once __DIR__ . '/../../backend/bootstrap.php';
// API ไลก์/บันทึก/ติดตาม/คอมเมนต์ เรียกจาก core.js, model.js → SocialController (ตอบกลับเป็น JSON)
App\Http\Kernel::run(App\Controllers\SocialController::class, 'handle');
