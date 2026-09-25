<?php

require_once __DIR__ . '/../backend/bootstrap.php';
App\Http\Kernel::run(App\Controllers\AdminController::class, 'dashboard');
