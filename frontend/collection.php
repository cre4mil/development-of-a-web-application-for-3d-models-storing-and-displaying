<?php
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['uid'])) { http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$mid = (int)($_POST['model_id'] ?? 0);
if ($mid <= 0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }

$uid = (int)$_SESSION['uid'];
$chk = $pdo->prepare("SELECT 1 FROM collections WHERE user_id=? AND model_id=?");
$chk->execute([$uid, $mid]);

if ($chk->fetch()) {
    $pdo->prepare("DELETE FROM collections WHERE user_id=? AND model_id=?")->execute([$uid, $mid]);
    echo json_encode(['saved'=>false]);
} else {
    $m = $pdo->prepare("SELECT id FROM models WHERE id=? AND is_public=1");
    $m->execute([$mid]);
    if (!$m->fetch()) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
    $pdo->prepare("INSERT INTO collections (user_id, model_id) VALUES (?,?)")->execute([$uid, $mid]);
    echo json_encode(['saved'=>true]);
}
