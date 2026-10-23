<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าแรก (แกลเลอรีโมเดล) → HomeController::index()
App\Http\Kernel::run(App\Controllers\HomeController::class, 'index');
