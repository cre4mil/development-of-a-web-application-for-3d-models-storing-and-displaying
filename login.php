<?php
require 'connect.php';
ob_start(); // กัน output ไปชน header

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['login_error'] = '';

if ($email === '' || $password === '') {
  $_SESSION['login_error'] = 'กรุณากรอกอีเมลและรหัสผ่าน';
  header('Location: index.php'); exit;
}

try {
  // ดึงผู้ใช้จากอีเมล
  $st = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u) {
    $_SESSION['login_error'] = 'ไม่พบบัญชีนี้';
    header('Location: index.php'); exit;
  }

  
  $hash = $u['password'];

  // ✅ รองรับฐานข้อมูลเดิมที่เก็บรหัสแบบ plain text:
  // - ถ้า $hash ดูเหมือนไม่ใช่ hash (ไม่มีรูปแบบ bcrypt/argon) จะเทียบแบบตรง ๆ ให้
  $looksHashed = preg_match('/^\$2y\$/', $hash) || preg_match('/^\$argon2(id|i)d?\$/', $hash);

  $ok = false;
  if ($looksHashed) {
    $ok = password_verify($password, $hash);
  } else {
    // เทียบตรง ๆ (สำหรับฐานข้อมูลเก่าที่เก็บ plain text)
    $ok = hash_equals($hash, $password);
  }

  if (!$ok) {
    $_SESSION['login_error'] = 'รหัสผ่านไม่ถูกต้อง';
    header('Location: index.php'); exit;
  }

  $_SESSION['uid']   = (int)$u['id'];
  $_SESSION['uname'] = $u['username'];
  $_SESSION['is_admin'] = (int)$u['is_admin'];
  session_regenerate_id(true);

  header('Location: index.php'); exit;

} catch (Throwable $e) {
  $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
  header('Location: index.php'); exit;
}

