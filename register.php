<?php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm_password'] ?? '';

  if ($username==='' || $email==='' || $pass==='' || $confirm==='') { 
    $_SESSION['reg_error'] = 'กรอกข้อมูลไม่ครบ';
    header("Location: index.php"); exit;
  }
  if ($pass !== $confirm) { 
    $_SESSION['reg_error'] = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    header("Location: index.php"); exit;
  }

  // เช็คว่าใช้อีเมล @gmail.com หรือ @hotmail.com หรือไม่
  if (!preg_match('/@(gmail\.com|hotmail\.com)$/i', $email)) { 
    $_SESSION['reg_error'] = 'อนุญาตให้ใช้อีเมล @gmail.com หรือ @hotmail.com เท่านั้น';
    header("Location: index.php"); exit;
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  // เช็คอีเมลซ้ำก่อน INSERT
  $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $chk->execute([$email]);
  if ($chk->fetch()) { 
    $_SESSION['reg_error'] = 'อีเมลนี้ถูกใช้แล้ว';
    header("Location: index.php"); exit;
  }

  $st = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?, ?, ?)");
  $st->execute([$username, $email, $hash]);

  $_SESSION['reg_success'] = 'สมัครบัญชีสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
  header("Location: index.php"); exit;
}
