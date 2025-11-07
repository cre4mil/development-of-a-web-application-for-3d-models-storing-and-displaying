<?php
require __DIR__ . '/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'unauthorized']);
  exit;
}

if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'bad_csrf']);
  exit;
}

$uid = (int)$_SESSION['uid'];
$mid = (int)($_POST['id'] ?? 0);
if ($mid <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'bad_id']);
  exit;
}

$st = $pdo->prepare("SELECT id, user_id, filename, thumb FROM models WHERE id=?");
$st->execute([$mid]);
$model = $st->fetch(PDO::FETCH_ASSOC);

if (!$model || (int)$model['user_id'] !== $uid) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'not_owner_or_not_found']);
  exit;
}

$pdo->beginTransaction();
try {
  $pdo->prepare("DELETE FROM likes        WHERE model_id=?")->execute([$mid]);
  $pdo->prepare("DELETE FROM collections  WHERE model_id=?")->execute([$mid]);

  $pdo->prepare("DELETE FROM models WHERE id=? AND user_id=?")->execute([$mid, $uid]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'db_error']);
  exit;
}

$deletedFiles = [];
$base = __DIR__ . '/uploads/';
$main = $base . $model['filename'];
$thumb= $base . ($model['thumb'] ?? '');

foreach ([$main,$thumb] as $path) {
  if ($path && is_file($path)) {
    @unlink($path);
    $deletedFiles[] = basename($path);
  }
}

echo json_encode(['ok'=>true,'deleted'=>$deletedFiles]);
