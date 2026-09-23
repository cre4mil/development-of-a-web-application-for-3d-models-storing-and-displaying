<?php
require_once dirname(__DIR__) . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'message'=>'method not allowed']); exit;
}

$uid = $_SESSION['uid'] ?? 0;
$id  = (int)($_POST['id'] ?? 0);
$to  = (int)($_POST['is_public'] ?? 1);

if (!$uid || !$id) {
  echo json_encode(['ok'=>false,'message'=>'bad request']); exit;
}

try {
  $st = $pdo->prepare("UPDATE models SET is_public=:pub WHERE id=:id AND user_id=:uid");
  $st->execute(['pub'=>$to, 'id'=>$id, 'uid'=>$uid]);

  if ($st->rowCount() > 0) {
    echo json_encode(['ok'=>true, 'id'=>$id, 'is_public'=>$to]);
  } else {
    echo json_encode(['ok'=>false, 'message'=>'update failed or not owner']);
  }
} catch (Throwable $e){
  echo json_encode(['ok'=>false, 'message'=>'db error']);
}
