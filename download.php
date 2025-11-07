<?php
require 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ตรวจสอบการส่งค่า id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  exit('ไม่พบโมเดลหรือคุณไม่มีสิทธิ์ดาวน์โหลด');
}

// ตรวจสอบสิทธิ์ผู้ใช้
$uid = $_SESSION['uid'] ?? 0;

// ดึงข้อมูลโมเดลจากฐานข้อมูล
$q = "SELECT * FROM models 
      WHERE id = :id 
      AND (is_public = 1 OR user_id = :uid)";
$st = $pdo->prepare($q);
$st->execute(['id' => $id, 'uid' => $uid]);
$model = $st->fetch(PDO::FETCH_ASSOC);

if (!$model) {
  exit('ไม่พบโมเดลหรือคุณไม่มีสิทธิ์ดาวน์โหลด');
}

// สร้างพาธไฟล์จริง
$filePath = __DIR__ . '/uploads/' . $model['filename'];

if (!is_file($filePath)) {
  exit('ไม่พบไฟล์บนเซิร์ฟเวอร์');
}

// ตั้งชื่อไฟล์ที่จะดาวน์โหลด
$downloadName = basename($model['filename']);

// ตั้ง header เพื่อดาวน์โหลด
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
flush();
readfile($filePath);
exit;
?>
