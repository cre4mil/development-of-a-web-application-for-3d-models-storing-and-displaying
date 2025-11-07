<?php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm_password'] ?? '';

  if ($username==='' || $email==='' || $pass==='' || $confirm==='') { die('กรอกข้อมูลไม่ครบ'); }
  if ($pass !== $confirm) { die('ยืนยันรหัสผ่านไม่ตรงกัน'); }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $st = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?, ?, ?)");
  $st->execute([$username, $email, $hash]);

  header("Location: index.php?success=1&open=login"); exit;
}
