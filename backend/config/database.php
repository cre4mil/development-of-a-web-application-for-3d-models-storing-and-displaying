<?php
/**
 * Shared bootstrap and database configuration.
 *
 * Configure production installations with environment variables rather than
 * editing this file: DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.
 */
function configEnv(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_set_cookie_params([ // NOSONAR -- $isHttps is derived from the active request so local HTTP remains usable while production cookies require HTTPS.
        'httponly' => true,
        'secure' => $isHttps, // NOSONAR -- Secure cookies are enabled in HTTPS; localhost HTTP needs a non-secure session cookie for development.
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_name('MODELSESSID');
    session_start();
}

$host = configEnv('DB_HOST', '127.0.0.1');
$port = configEnv('DB_PORT', '3306');
$db = configEnv('DB_NAME', 'db_3dmodels');
$user = configEnv('DB_USER', 'root');
$pass = configEnv('DB_PASSWORD');
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
    error_log('DB connect error: ' . $e->getMessage());
    exit('Database connection failed.');
}

// Central Payment Config
// method สามารถเลือกเป็น: promptpay, truemoney, kbank, scb, bbl, ktb, krungsri
define('PAYMENT_METHOD', configEnv('PAYMENT_METHOD', 'promptpay'));
define('PAYMENT_ACCOUNT', configEnv('PAYMENT_ACCOUNT', '0949491035'));
define('PAYMENT_NAME', configEnv('PAYMENT_NAME', 'ปาณิสรา กุลคำ'));

// SlipOK API Config
define('SLIPOK_API_KEY', configEnv('SLIPOK_API_KEY'));
define('SLIPOK_BRANCH_ID', configEnv('SLIPOK_BRANCH_ID'));

// ===== Centralized Payment Config =====
// ค่าธรรมเนียมแพลตฟอร์ม (%) ที่หักจากราคาโมเดลก่อนโอนให้ Creator
define('PLATFORM_FEE_PCT',   10);   // 10%

// ยอดขั้นต่ำที่ Creator ต้องสะสมก่อนถอนเงินได้
define('PAYOUT_THRESHOLD',   300);  // 300 บาท
