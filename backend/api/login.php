<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Authentication.php';
use App\Services\Authentication;
const HOME_REDIRECT = 'Location: index.php';
ob_start(); // กัน output ไปชน header

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header(HOME_REDIRECT); exit; }

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['login_error'] = '';

if ($email === '' || $password === '') {
  $_SESSION['login_error'] = 'กรุณากรอกอีเมลและรหัสผ่าน';
  header(HOME_REDIRECT); exit;
}

try {
  // ดึงผู้ใช้จากอีเมล
  $st = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u) {
    $_SESSION['login_error'] = 'ไม่พบบัญชีนี้';
    header(HOME_REDIRECT); exit;
  }


  $hash = $u['password'];

  // ✅ รองรับฐานข้อมูลเดิมที่เก็บรหัสแบบ plain text:
  // - ถ้า $hash ดูเหมือนไม่ใช่ hash (ไม่มีรูปแบบ bcrypt/argon) จะเทียบแบบตรง ๆ ให้
  $ok = Authentication::verifyPassword($password, $hash);

  if (!$ok) {
    $_SESSION['login_error'] = 'รหัสผ่านไม่ถูกต้อง';
    header(HOME_REDIRECT); exit;
  }

  $_SESSION['uid']   = (int)$u['id'];
  $_SESSION['uname'] = $u['username'];
  $_SESSION['is_admin'] = (int)$u['is_admin'];
  session_regenerate_id(true);

  header(HOME_REDIRECT); exit;

} catch (Throwable $e) {
  $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
  header(HOME_REDIRECT); exit;
}

