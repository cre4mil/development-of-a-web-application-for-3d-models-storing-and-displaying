<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// ดาวน์โหลดไฟล์โมเดล → ModelController::download()
App\Http\Kernel::run(App\Controllers\ModelController::class, 'download');
