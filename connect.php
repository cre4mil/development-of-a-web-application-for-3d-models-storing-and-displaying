<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_name('MODELSESSID'); 
    session_start();
}

$host = '127.0.0.1';   
$port = '3307';
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

