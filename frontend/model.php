<?php

require_once __DIR__ . '/../backend/bootstrap.php';
// หน้ารายละเอียดโมเดล + ตัวดู 3D → ModelController::show()
App\Http\Kernel::run(App\Controllers\ModelController::class, 'show');
