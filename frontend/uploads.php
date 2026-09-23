<?php
require_once 'connect.php';
require_once __DIR__ . '/../backend/services/Security.php';
require_once __DIR__ . '/../backend/services/UploadValidator.php';
use App\Services\UploadValidator;
if (!isset($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

const DEFAULT_LICENSE = 'CC BY';
const UPLOAD_FORM_REDIRECT = 'Location: index.php?openUpload=1';
$title   = trim($_POST['title'] ?? '');
$desc    = trim($_POST['description'] ?? '');
$license = trim($_POST['license'] ?? DEFAULT_LICENSE);
$tagsRaw = trim($_POST['tags'] ?? '');
$price   = max(0, (float)($_POST['price'] ?? 0));
$model   = $_FILES['model'] ?? null;
$thumb   = $_FILES['thumb'] ?? null;

$validLicenses = [DEFAULT_LICENSE,'CC BY-SA','CC BY-ND','CC BY-NC','CC BY-NC-SA','CC BY-NC-ND','CC0','All Rights Reserved'];
if (!in_array($license, $validLicenses)) {
    $license = DEFAULT_LICENSE;
}

$err = UploadValidator::validateModel($model);

if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
  $err = 'ขนาดไฟล์รวมทั้งหมดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ (เกิน '.ini_get('post_max_size').')';
}

if (!$err) {
  $modelExt = strtolower(pathinfo($model['name'], PATHINFO_EXTENSION));
}

$hasThumb = ($thumb && $thumb['error'] !== UPLOAD_ERR_NO_FILE);
if (!$err) {
  $err = UploadValidator::validateThumbnail($thumb);
  if (!$err) {
    $thumbExt = strtolower(pathinfo($thumb['name'], PATHINFO_EXTENSION));
  }
}

if ($err) {
  $_SESSION['upload_error'] = $err;
  header(UPLOAD_FORM_REDIRECT); exit;
}

$rand      = bin2hex(random_bytes(8));
$modelName = $rand.'.'.$modelExt;
$destModel = __DIR__."/uploads/$modelName";
if (!is_dir(__DIR__.'/uploads')) {
  @mkdir(__DIR__.'/uploads', 0775, true);
}
if (!move_uploaded_file($model['tmp_name'], $destModel)) {
  $_SESSION['upload_error'] = 'บันทึกไฟล์โมเดลไม่สำเร็จ';
  header(UPLOAD_FORM_REDIRECT); exit;
}

$thumbName = null;
if ($hasThumb) {
  $thumbName = $rand.'_thumb.'.$thumbExt;
  if (!move_uploaded_file($thumb['tmp_name'], __DIR__."/uploads/$thumbName")) {
    @unlink($destModel);
    $_SESSION['upload_error'] = 'บันทึกรูปภาพไม่สำเร็จ';
    header(UPLOAD_FORM_REDIRECT); exit;
  }
}

$stmt = $pdo->prepare("INSERT INTO models (user_id,title,filename,thumb,description,license,price) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$_SESSION['uid'], $title, $modelName, $thumbName, $desc, $license, $price]);
$newModelId = $pdo->lastInsertId();

// บันทึก Tags
if ($tagsRaw !== '') {
  $tags = array_unique(array_filter(array_map(fn($t)=>mb_strtolower(trim($t)), explode(',', $tagsRaw))));
  $insTag = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
  $selTag = $pdo->prepare("SELECT id FROM tags WHERE name=?");
  $insMT  = $pdo->prepare("INSERT IGNORE INTO model_tags (model_id,tag_id) VALUES (?,?)");
  foreach ($tags as $tag) {
    if ($tag==='') {
      continue;
    }
    $insTag->execute([$tag]);
    $selTag->execute([$tag]);
    $tid = $selTag->fetchColumn();
    if ($tid) {
      $insMT->execute([$newModelId, $tid]);
    }
  }
}

header('Location: index.php?uploads=1'); exit;
