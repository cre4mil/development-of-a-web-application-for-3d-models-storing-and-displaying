<?php
require_once dirname(__DIR__) . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['uid'] ?? 0);

// GET — ดึงรายการคอมเม้น
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mid = (int)($_GET['model_id'] ?? 0);
    if ($mid <= 0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $st = $pdo->prepare("
        SELECT c.id, c.body, c.created_at, u.username, c.user_id
        FROM comments c JOIN users u ON u.id = c.user_id
        WHERE c.model_id = ?
        ORDER BY c.created_at ASC
    ");
    $st->execute([$mid]);
    echo json_encode(['ok'=>1, 'comments'=>$st->fetchAll(), 'uid'=>$uid]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'method']); exit; }
if (!$uid) { http_response_code(401); echo json_encode(['error'=>'not_login']); exit; }

$action = $_POST['action'] ?? 'add';

// ลบคอมเม้น (เฉพาะของตัวเอง)
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }
    $pdo->prepare("DELETE FROM comments WHERE id=? AND user_id=?")->execute([$id, $uid]);
    echo json_encode(['ok'=>1]);
    exit;
}

// เพิ่มคอมเม้น
$mid  = (int)($_POST['model_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
if ($mid <= 0 || $body === '') { http_response_code(400); echo json_encode(['error'=>'missing']); exit; }
if (mb_strlen($body) > 1000)   { http_response_code(400); echo json_encode(['error'=>'too_long']); exit; }

$chk = $pdo->prepare("SELECT id FROM models WHERE id=? AND (is_public=1 OR user_id=?)");
$chk->execute([$mid, $uid]);
if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }

$pdo->prepare("INSERT INTO comments (model_id, user_id, body) VALUES (?,?,?)")->execute([$mid, $uid, $body]);
$nid = $pdo->lastInsertId();

$st = $pdo->prepare("SELECT c.id, c.body, c.created_at, u.username, c.user_id FROM comments c JOIN users u ON u.id=c.user_id WHERE c.id=?");
$st->execute([$nid]);
echo json_encode(['ok'=>1, 'comment'=>$st->fetch()]);
