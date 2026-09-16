<?php
require 'connect.php';
header('Content-Type: application/json; charset=utf-8');

$validLicenses = ['CC BY','CC BY-SA','CC BY-ND','CC BY-NC','CC BY-NC-SA','CC BY-NC-ND','CC0','All Rights Reserved'];

function loadModelOwned($pdo,$id,$uid){
  $st=$pdo->prepare("SELECT id,user_id,title,filename,thumb,description,license,price FROM models WHERE id=? AND user_id=?");
  $st->execute([$id,$uid]); return $st->fetch();
}

function saveTags($pdo,$mid,$raw){
  $pdo->prepare("DELETE FROM model_tags WHERE model_id=?")->execute([$mid]);
  if(trim($raw)==='') return;
  $tags=array_unique(array_filter(array_map(fn($t)=>mb_strtolower(trim($t)),explode(',',$raw))));
  $ins=$pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
  $sel=$pdo->prepare("SELECT id FROM tags WHERE name=?");
  $imt=$pdo->prepare("INSERT IGNORE INTO model_tags (model_id,tag_id) VALUES (?,?)");
  foreach($tags as $t){ if($t==='') continue; $ins->execute([$t]); $sel->execute([$t]); $tid=$sel->fetchColumn(); if($tid) $imt->execute([$mid,$tid]); }
}

if(($_GET['action']??'')==='get'){
  if(!isset($_SESSION['uid'])){ http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
  $id=(int)($_GET['id']??0);
  if($id<=0){ http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
  $m=loadModelOwned($pdo,$id,$_SESSION['uid']);
  if(!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
  // fetch tags
  $st=$pdo->prepare("SELECT t.name FROM tags t JOIN model_tags mt ON mt.tag_id=t.id WHERE mt.model_id=?");
  $st->execute([$id]);
  $m['tags']=implode(', ',$st->fetchAll(PDO::FETCH_COLUMN));
  echo json_encode($m); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!isset($_SESSION['uid'])){ http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
  $csrf=$_POST['csrf']??'';
  if(empty($csrf)||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$csrf)){ http_response_code(403); echo json_encode(['error'=>'bad_csrf']); exit; }
  $action=$_POST['action']??'';

  if($action==='update'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $m=loadModelOwned($pdo,$id,$_SESSION['uid']);
    if(!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
    $title=trim($_POST['title']??'');
    $desc=trim($_POST['description']??'');
    $license=trim($_POST['license']??'CC BY');
    if(!in_array($license,$validLicenses)) $license='CC BY';
    $price=max(0, round(floatval($_POST['price'] ?? 0), 2));
    $tagsRaw=trim($_POST['tags']??'');
    $allowedModelExt=['obj','glb','gltf','fbx','blend','stl','usdz']; $allowedImgExt=['jpg','jpeg','png'];
    $filename=$m['filename']; $thumb=$m['thumb'];
    if(!empty($_FILES['model'])&&$_FILES['model']['error']===UPLOAD_ERR_OK&&$_FILES['model']['size']>0){
      $ext=strtolower(pathinfo($_FILES['model']['name'],PATHINFO_EXTENSION));
      if(!in_array($ext,$allowedModelExt)){ echo json_encode(['error'=>'model_ext']); exit; }
      $new=bin2hex(random_bytes(8)).'.'.$ext;
      if(!move_uploaded_file($_FILES['model']['tmp_name'],__DIR__."/uploads/$new")){ echo json_encode(['error'=>'model_save']); exit; }
      if($filename&&file_exists(__DIR__."/uploads/$filename")) @unlink(__DIR__."/uploads/$filename");
      $filename=$new;
      
      // Auto-generation via Blender
      $blenderPath = '"C:\Program Files\Blender Foundation\Blender 5.2\blender.exe"';
      $scriptPath  = __DIR__ . '/convert.py';
      $originalFile = __DIR__ . '/uploads/' . $filename;
      $gltfName = null; $glbName = null; $usdzName = null; $objName = null;
      $formatsToConvert = ['glb', 'obj', 'gltf', 'usdz'];
      
      $outputArgs = "";
      $generatedFiles = [];
      foreach ($formatsToConvert as $ext2) {
          if ($ext === $ext2) {
              if ($ext2 === 'glb') $glbName = $filename;
              elseif ($ext2 === 'obj') $objName = $filename;
              elseif ($ext2 === 'gltf') $gltfName = $filename;
              elseif ($ext2 === 'usdz') $usdzName = $filename;
              continue;
          }
          $tempName = pathinfo($filename, PATHINFO_FILENAME) . '_converted.' . $ext2;
          $outputPath = __DIR__ . '/uploads/' . $tempName;
          $outputArgs .= " \"$outputPath\"";
          $generatedFiles[$ext2] = $tempName;
      }
      
      if (!empty($outputArgs)) {
          $command = "$blenderPath -b -P \"$scriptPath\" -- \"$originalFile\"" . $outputArgs . " 2>&1";
          shell_exec($command);
          
          foreach ($generatedFiles as $ext2 => $tempName) {
              $outputPath = __DIR__ . '/uploads/' . $tempName;
              if (file_exists($outputPath)) {
                  if ($ext2 === 'glb') $glbName = $tempName;
                  elseif ($ext2 === 'obj') $objName = $tempName;
                  elseif ($ext2 === 'gltf') $gltfName = $tempName;
                  elseif ($ext2 === 'usdz') $usdzName = $tempName;
              }
          }
      }
      $pdo->prepare("UPDATE models SET file_gltf=?, file_glb=?, file_usdz=?, file_obj=? WHERE id=? AND user_id=?")->execute([$gltfName, $glbName, $usdzName, $objName, $id, $_SESSION['uid']]);
    }
    if(!empty($_FILES['thumb'])&&$_FILES['thumb']['error']===UPLOAD_ERR_OK&&$_FILES['thumb']['size']>0){
      $ext=strtolower(pathinfo($_FILES['thumb']['name'],PATHINFO_EXTENSION));
      if(!in_array($ext,$allowedImgExt)){ echo json_encode(['error'=>'thumb_ext']); exit; }
      $new=bin2hex(random_bytes(8)).'_thumb.'.$ext;
      if(!move_uploaded_file($_FILES['thumb']['tmp_name'],__DIR__."/uploads/$new")){ echo json_encode(['error'=>'thumb_save']); exit; }
      if($thumb&&file_exists(__DIR__."/uploads/$thumb")) @unlink(__DIR__."/uploads/$thumb");
      $thumb=$new;
    }
    $pdo->prepare("UPDATE models SET title=?,description=?,filename=?,thumb=?,license=?,price=? WHERE id=? AND user_id=?")
        ->execute([$title,$desc,$filename,$thumb,$license,$price,$id,$_SESSION['uid']]);
    saveTags($pdo,$id,$tagsRaw);
    echo json_encode(['ok'=>1,'title'=>$title]); exit;
  }

  if($action==='delete'){
    $id=(int)($_POST['id']??0);
    if($id<=0){ http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $m=loadModelOwned($pdo,$id,$_SESSION['uid']);
    if(!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
    if(!empty($m['filename'])&&file_exists(__DIR__."/uploads/{$m['filename']}")) @unlink(__DIR__."/uploads/{$m['filename']}");
    if(!empty($m['thumb'])&&file_exists(__DIR__."/uploads/{$m['thumb']}")) @unlink(__DIR__."/uploads/{$m['thumb']}");
    $pdo->prepare("DELETE FROM models WHERE id=? AND user_id=?")->execute([$id,$_SESSION['uid']]);
    echo json_encode(['ok'=>1]); exit;
  }
}

http_response_code(400); echo json_encode(['error'=>'bad_action']);

/* ============================================================
   OLD CODE (v1) — ก่อนเพิ่ม License, Tags และ saveTags()
   ไม่ได้ใช้งานแล้ว เก็บไว้เพื่อดูความเปลี่ยนแปลง
   ============================================================

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
  // v1: ไม่มีการ fetch tags
  echo json_encode($m); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_SESSION['uid'])) { http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
  $csrf = $_POST['csrf'] ?? '';
  if (empty($csrf) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403); echo json_encode(['error'=>'bad_csrf']); exit;
  }
  $action = $_POST['action'] ?? '';

  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $m = loadModelOwned($pdo,$id,$_SESSION['uid']);
    if (!$m){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $allowedModelExt=['obj','glb','gltf']; $allowedImgExt=['jpg','jpeg','png'];
    $filename=$m['filename']; $thumb=$m['thumb'];
    if (!empty($_FILES['model']) && $_FILES['model']['error']===UPLOAD_ERR_OK && $_FILES['model']['size']>0) {
      $ext=strtolower(pathinfo($_FILES['model']['name'],PATHINFO_EXTENSION));
      if (!in_array($ext,$allowedModelExt)) { echo json_encode(['error'=>'model_ext']); exit; }
      $new=bin2hex(random_bytes(8)).'.'.$ext;
      if (!move_uploaded_file($_FILES['model']['tmp_name'],__DIR__."/uploads/$new")) { echo json_encode(['error'=>'model_save']); exit; }
      if ($filename && file_exists(__DIR__."/uploads/$filename")) @unlink(__DIR__."/uploads/$filename");
      $filename=$new;
    }
    if (!empty($_FILES['thumb']) && $_FILES['thumb']['error']===UPLOAD_ERR_OK && $_FILES['thumb']['size']>0) {
      $ext=strtolower(pathinfo($_FILES['thumb']['name'],PATHINFO_EXTENSION));
      if (!in_array($ext,$allowedImgExt)) { echo json_encode(['error'=>'thumb_ext']); exit; }
      $new=bin2hex(random_bytes(8)).'_thumb.'.$ext;
      if (!move_uploaded_file($_FILES['thumb']['tmp_name'],__DIR__."/uploads/$new")) { echo json_encode(['error'=>'thumb_save']); exit; }
      if ($thumb && file_exists(__DIR__."/uploads/$thumb")) @unlink(__DIR__."/uploads/$thumb");
      $thumb=$new;
    }
    // v1: UPDATE ไม่มี license
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
    if (!empty($m['thumb'])    && file_exists(__DIR__."/uploads/{$m['thumb']}"))    @unlink(__DIR__."/uploads/{$m['thumb']}");
    $pdo->prepare("DELETE FROM models WHERE id=? AND user_id=?")->execute([$id,$_SESSION['uid']]);
    echo json_encode(['ok'=>1]); exit;
  }
}
http_response_code(400); echo json_encode(['error'=>'bad_action']);

   สิ่งที่เพิ่มใน v2:
   - เพิ่ม $validLicenses[] และ validate license
   - เพิ่ม function saveTags($pdo, $mid, $raw) สำหรับจัดการ tags
   - GET ?action=get ส่ง tags กลับมาด้วย
   - UPDATE เพิ่ม license
   - เรียก saveTags() หลัง UPDATE
============================================================ */
