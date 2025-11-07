<?php
require 'connect.php';
session_start();

if (!isset($_SESSION['uid'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');
$model = $_FILES['model'] ?? null;   
$thumb = $_FILES['thumb'] ?? null;   

$err = null;

$allowedModelExt = ['obj','glb','gltf'];
$allowedImgExt   = ['jpg','jpeg','png']; 

if (!$model || $model['error'] === UPLOAD_ERR_NO_FILE) {
  $err = 'กรุณาตรวจสอบไฟล์โมเดล';
} elseif ($model['error'] !== UPLOAD_ERR_OK) {
  $err = 'อัปโหลดไฟล์โมเดลไม่สำเร็จ (รหัส '.$model['error'].')';
}

if (!$err) {
  $modelExt = strtolower(pathinfo($model['name'], PATHINFO_EXTENSION));
  if (!in_array($modelExt, $allowedModelExt)) {
    $err = 'ไฟล์โมเดลต้องเป็น .obj .glb หรือ .gltf';
  }
}

$hasThumb = ($thumb && $thumb['error'] !== UPLOAD_ERR_NO_FILE);
if (!$err && $hasThumb) {
  if ($thumb['error'] !== UPLOAD_ERR_OK) {
    $err = 'อัปโหลดรูปภาพไม่สำเร็จ (รหัส '.$thumb['error'].')';
  } else {
    $thumbExt = strtolower(pathinfo($thumb['name'], PATHINFO_EXTENSION));
    if (!in_array($thumbExt, $allowedImgExt)) {
      $err = 'รูปต้องเป็น .jpg หรือ .png';
    }
  }
}

if ($err) {
  $_SESSION['upload_error'] = $err;
  $_SESSION['upload_old']   = ['title'=>$title,'description'=>$desc];
  header('Location: index.php?openUpload=1');
  exit;
}

$rand = bin2hex(random_bytes(8));
$modelName = $rand.'.'.$modelExt;
$destModel = __DIR__."/uploads/$modelName";

if (!is_dir(__DIR__."/uploads")) { @mkdir(__DIR__."/uploads", 0775, true); }
if (!move_uploaded_file($model['tmp_name'], $destModel)) {
  $_SESSION['upload_error'] = 'บันทึกไฟล์โมเดลไม่สำเร็จ';
  header('Location: index.php?openUpload=1'); exit;
}

$thumbName = null;
if ($hasThumb) {
  $thumbName = $rand.'_thumb.'.$thumbExt;
  $destThumb = __DIR__."/uploads/$thumbName";
  if (!move_uploaded_file($thumb['tmp_name'], $destThumb)) {
    @unlink($destModel);
    $_SESSION['upload_error'] = 'บันทึกรูปภาพไม่สำเร็จ';
    header('Location: index.php?openUpload=1'); exit;
  }
}

$stmt = $pdo->prepare("
  INSERT INTO models (user_id, title, filename, thumb, description)
  VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$_SESSION['uid'], $title, $modelName, $thumbName, $desc]);

$_SESSION['upload_success'] = 1;
header('Location: index.php?uploads=1');
exit;
