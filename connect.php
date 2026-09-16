<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('MODELSESSID'); 
    session_start();
}

$host = '127.0.0.1';   
$port = '3306';
$db      = 'db_3dmodels'; 
$user    = 'root';        
$pass    = '';            
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB connect error: ' . $e->getMessage());
}

// Central Payment Config
// method สามารถเลือกเป็น: promptpay, truemoney, kbank, scb, bbl, ktb, krungsri
define('PAYMENT_METHOD',  'promptpay'); 
define('PAYMENT_ACCOUNT', '0949491035');
define('PAYMENT_NAME',    'ปาณิสรา กุลคำ');

// SlipOK API Config
define('SLIPOK_API_KEY', ''); // ใส่ API Key จาก SlipOK ถ้าต้องการตรวจสอบอัตโนมัติ
define('SLIPOK_BRANCH_ID', ''); // สาขา (ถ้ามี)

// ===== Centralized Payment Config =====
// ค่าธรรมเนียมแพลตฟอร์ม (%) ที่หักจากราคาโมเดลก่อนโอนให้ Creator
define('PLATFORM_FEE_PCT',   10);   // 10%

// ยอดขั้นต่ำที่ Creator ต้องสะสมก่อนถอนเงินได้
define('PAYOUT_THRESHOLD',   300);  // 300 บาท

