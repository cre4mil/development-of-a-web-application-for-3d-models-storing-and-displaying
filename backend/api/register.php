<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/Security.php';
require_once dirname(__DIR__) . '/services/Authentication.php';
use App\Services\Authentication;
const HOME_REDIRECT = 'Location: index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm_password'] ?? '';

  $registrationError = Authentication::validateRegistration($username, $email, $pass, $confirm);
  if ($registrationError !== null) {
    $_SESSION['reg_error'] = $registrationError;
    header(HOME_REDIRECT); exit;
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  // เช็คอีเมลซ้ำก่อน INSERT
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) {
    $_SESSION['reg_error'] = 'อีเมลนี้ถูกใช้แล้ว';
    header(HOME_REDIRECT); exit;
  }

  $st = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?, ?, ?)");
  $st->execute([$username, $email, $hash]);

  $_SESSION['reg_success'] = 'สมัครบัญชีสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
  header(HOME_REDIRECT); exit;
}
