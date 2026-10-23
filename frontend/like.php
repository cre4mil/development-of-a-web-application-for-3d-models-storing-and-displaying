<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้าโมเดลที่ถูกใจ → ProfileController::liked()
App\Http\Kernel::run(App\Controllers\ProfileController::class, 'liked');
