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

// ตรวจสอบพารามิเตอร์ format
$format = isset($_GET['format']) ? $_GET['format'] : 'original';

// ===== Payment Gate: ตรวจสอบว่าโมเดลต้องชำระเงินหรือไม่ =====
if (isset($model['price']) && $model['price'] > 0 && (int)$model['user_id'] !== $uid) {
  function showDeniedPage($title, $msg, $modelId) {
      echo '<!DOCTYPE html>
      <html lang="th">
      <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>'.$title.'</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <style>
          body { background: #16161a; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: "Segoe UI", sans-serif; }
          .error-box { background: #242629; padding: 3rem; border-radius: 20px; text-align: center; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
          .error-icon { font-size: 4rem; color: #ef4565; margin-bottom: 1rem; }
          h2 { font-weight: 700; margin-bottom: 1rem; }
          p { color: #94a1b2; margin-bottom: 2rem; }
          .btn-custom { background: #7f5af0; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 30px; font-weight: 600; text-decoration: none; transition: 0.2s; }
          .btn-custom:hover { background: #2cb67d; color: #fff; }
        </style>
      </head>
      <body>
        <div class="error-box">
          <i class="bi bi-shield-lock-fill error-icon"></i>
          <h2>'.$title.'</h2>
          <p>'.$msg.'</p>
          <a href="model.php?id='.(int)$modelId.'" class="btn-custom">กลับไปที่หน้าโมเดล</a>
        </div>
      </body>
      </html>';
      exit;
  }

  if (!$uid) {
    showDeniedPage('คุณยังไม่ได้เข้าสู่ระบบ', 'กรุณาเข้าสู่ระบบก่อนทำการสั่งซื้อและดาวน์โหลดโมเดลนี้', $id);
  }
  
  $isAdmin = false;
  if ($uid) {
    $adminCheck = $pdo->prepare("SELECT is_admin FROM users WHERE id=?");
    $adminCheck->execute([$uid]);
    $isAdmin = (bool)$adminCheck->fetchColumn();
  }

  if (!$isAdmin) {
    // ตรวจผ่าน order_items (Centralized Payment Model)
    $chk = $pdo->prepare("
      SELECT 1 FROM order_items oi
      JOIN orders o ON o.id = oi.order_id
      WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status = 'approved'
      LIMIT 1
    ");
    $chk->execute([$uid, $id]);
    if (!$chk->fetch()) {
      showDeniedPage('ไม่สามารถดาวน์โหลดได้', 'โมเดลนี้มีการตั้งราคาไว้ กรุณาชำระเงินและรอให้ผู้ดูแลระบบอนุมัติก่อนดาวน์โหลด', $id);
    }
  }
}
// ============================================================

$filenameToDownload = $model['filename']; // ค่าเริ่มต้น

if ($format === 'gltf' && !empty($model['file_gltf'])) {
  $filenameToDownload = $model['file_gltf'];
} elseif ($format === 'glb' && !empty($model['file_glb'])) {
  $filenameToDownload = $model['file_glb'];
} elseif ($format === 'usdz' && !empty($model['file_usdz'])) {
  $filenameToDownload = $model['file_usdz'];
} elseif ($format === 'obj' && !empty($model['file_obj'])) {
  $filenameToDownload = $model['file_obj'];
}

// สร้างพาธไฟล์จริง
$filePath = __DIR__ . '/uploads/' . $filenameToDownload;

if (!is_file($filePath)) {
  exit('ไม่พบไฟล์บนเซิร์ฟเวอร์');
}

// ตั้งชื่อไฟล์ที่จะดาวน์โหลด
$downloadName = basename($filenameToDownload);

// ตั้ง header เพื่อดาวน์โหลด
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
flush();
readfile($filePath);
exit;
?>
