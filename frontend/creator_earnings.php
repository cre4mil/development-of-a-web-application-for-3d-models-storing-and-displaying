<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้ารายได้ครีเอเตอร์ + บันทึกบัญชีธนาคาร → EarningsController::index()
App\Http\Kernel::run(App\Controllers\EarningsController::class, 'index');
