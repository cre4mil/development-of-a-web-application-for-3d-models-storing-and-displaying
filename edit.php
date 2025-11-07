<?php
require 'connect.php';
header('Content-Type: application/json; charset=utf-8');

function loadModelOwned($pdo,$id,$uid){
  $st=$pdo->prepare("SELECT id,user_id,title,filename,thumb,description FROM models WHERE id=? AND user_id=?");
  $st->execute([$id,$uid]); return $st->fetch();
}

if (($_GET['action'] ?? '') === 'get') {
  if (!isset($_SESSION['uid'])) { http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
  $id = (int)($_GET['id'] ?? 0);
  if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
  $m = loadModelOwned($pdo,$id,$_SESSION['uid']);
  if (!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
  echo json_encode($m); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_SESSION['uid'])) { http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
  $action = $_POST['action'] ?? '';

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $m = loadModelOwned($pdo,$id,$_SESSION['uid']);
    if (!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }

    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $allowedModelExt = ['obj','glb','gltf'];
    $allowedImgExt   = ['jpg','jpeg','png'];
    $filename = $m['filename']; $thumb = $m['thumb'];

    if (!empty($_FILES['model']) && $_FILES['model']['error']===UPLOAD_ERR_OK && $_FILES['model']['size']>0) {
      $ext = strtolower(pathinfo($_FILES['model']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,$allowedModelExt)) { echo json_encode(['error'=>'model_ext']); exit; }
      $new = bin2hex(random_bytes(8)).'.'.$ext;
      if (!move_uploaded_file($_FILES['model']['tmp_name'], __DIR__."/uploads/$new")) { echo json_encode(['error'=>'model_save']); exit; }
      if ($filename && file_exists(__DIR__."/uploads/$filename")) @unlink(__DIR__."/uploads/$filename");
      $filename = $new;
    }

    if (!empty($_FILES['thumb']) && $_FILES['thumb']['error']===UPLOAD_ERR_OK && $_FILES['thumb']['size']>0) {
      $ext = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,$allowedImgExt)) { echo json_encode(['error'=>'thumb_ext']); exit; }
      $new = bin2hex(random_bytes(8)).'_thumb.'.$ext;
      if (!move_uploaded_file($_FILES['thumb']['tmp_name'], __DIR__."/uploads/$new")) { echo json_encode(['error'=>'thumb_save']); exit; }
      if ($thumb && file_exists(__DIR__."/uploads/$thumb")) @unlink(__DIR__."/uploads/$thumb");
      $thumb = $new;
    }

    $pdo->prepare("UPDATE models SET title=?, description=?, filename=?, thumb=? WHERE id=? AND user_id=?")
        ->execute([$title,$desc,$filename,$thumb,$id,$_SESSION['uid']]);

    echo json_encode(['ok'=>1]); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $m = loadModelOwned($pdo,$id,$_SESSION['uid']);
    if (!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }

    if (!empty($m['filename']) && file_exists(__DIR__."/uploads/{$m['filename']}")) @unlink(__DIR__."/uploads/{$m['filename']}");
    if (!empty($m['thumb']) && file_exists(__DIR__."/uploads/{$m['thumb']}"))       @unlink(__DIR__."/uploads/{$m['thumb']}");
    $pdo->prepare("DELETE FROM models WHERE id=? AND user_id=?")->execute([$id,$_SESSION['uid']]);

    echo json_encode(['ok'=>1]); exit;
  }
}

http_response_code(400); echo json_encode(['error'=>'bad_action']);
