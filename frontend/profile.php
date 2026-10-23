<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าโปรไฟล์ → ProfileController::index()
App\Http\Kernel::run(App\Controllers\ProfileController::class, 'index');
