<?php
/**
 * cart.php — Cart API (Session-based)
 *
 * GET  action=get      — ดูรายการในตะกร้า + ราคา
 * POST action=add      — เพิ่มโมเดลลงตะกร้า
 * POST action=remove   — ลบโมเดลออกจากตะกร้า
 * POST action=clear    — ล้างตะกร้าทั้งหมด
 * GET  action=count    — นับจำนวนรายการ (สำหรับ badge)
 */
require_once 'connect.php';
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) {
    http_response_code(401);
    echo json_encode(['error' => 'not_login']);
    exit;
}

// Cart เก็บใน session เป็น array ของ model_id
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$method = $_SERVER['REQUEST_METHOD'];

// ========== GET ==========
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'get';

    if ($action === 'count') {
        echo json_encode(['count' => count($_SESSION['cart'])]);
        exit;
    }

    // action=get: ดึงรายละเอียดโมเดลทั้งหมดในตะกร้า
    $cartIds = $_SESSION['cart'];
    if (empty($cartIds)) {
        echo json_encode(['items' => [], 'total' => 0]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $st = $pdo->prepare("
        SELECT m.id, m.title, m.thumb, m.price, u.username AS creator_name, u.id AS creator_id
        FROM models m
        JOIN users u ON u.id = m.user_id
        WHERE m.id IN ($placeholders) AND m.is_public = 1 AND m.price > 0
        ORDER BY FIELD(m.id, $placeholders)
    ");
    $st->execute(array_merge($cartIds, $cartIds));
    $items = $st->fetchAll();

    // กรอง: ลบโมเดลที่เจ้าของตัวเองเป็นผู้ซื้อ + โมเดลที่ซื้อไปแล้ว
    $ownedCheck = $pdo->prepare("
        SELECT oi.model_id
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.buyer_id = ? AND o.payment_status = 'approved'
    ");
    $ownedCheck->execute([$uid]);
    $alreadyOwned = array_column($ownedCheck->fetchAll(), 'model_id');

    $filteredItems = [];
    foreach ($items as $item) {
        // ข้ามโมเดลของตัวเอง
        if ((int)$item['creator_id'] === $uid) {
            continue;
        }
        // ข้ามโมเดลที่ซื้อแล้ว
        if (in_array((int)$item['id'], array_map('intval', $alreadyOwned))) {
            continue;
        }
        $filteredItems[] = $item;
    }

    $total = array_sum(array_column($filteredItems, 'price'));
    $feePct = PLATFORM_FEE_PCT;

    echo json_encode([
        'items'        => $filteredItems,
        'total'        => round($total, 2),
        'platform_fee_pct' => $feePct,
        'count'        => count($filteredItems),
    ]);
    exit;
}

// ========== POST ==========
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $modelId = (int)($_POST['model_id'] ?? 0);
        if ($modelId <= 0) {
            echo json_encode(['error' => 'bad_model_id']); exit;
        }

        // ตรวจว่าโมเดลมีอยู่จริง และเป็นสินค้าที่ต้องจ่ายเงิน
        $st = $pdo->prepare("SELECT id, user_id, price, title FROM models WHERE id = ? AND is_public = 1 AND price > 0");
        $st->execute([$modelId]);
        $model = $st->fetch();

        if (!$model) {
            echo json_encode(['error' => 'model_not_found_or_free']); exit;
        }
        if ((int)$model['user_id'] === $uid) {
            echo json_encode(['error' => 'own_model']); exit;
        }

        // ตรวจว่าซื้อไปแล้วหรือยัง
        $chk = $pdo->prepare("
            SELECT 1 FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status = 'approved'
            LIMIT 1
        ");
        $chk->execute([$uid, $modelId]);
        if ($chk->fetch()) {
            echo json_encode(['error' => 'already_purchased']); exit;
        }

        // เพิ่มลงตะกร้า (ไม่ซ้ำ)
        if (!in_array($modelId, $_SESSION['cart'])) {
            $_SESSION['cart'][] = $modelId;
        }

        echo json_encode([
            'ok'    => 1,
            'count' => count($_SESSION['cart']),
            'message' => "เพิ่ม \"{$model['title']}\" ลงตะกร้าแล้ว"
        ]);
        exit;
    }

    if ($action === 'remove') {
        $modelId = (int)($_POST['model_id'] ?? 0);
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($id) => $id !== $modelId));
        echo json_encode(['ok' => 1, 'count' => count($_SESSION['cart'])]);
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        echo json_encode(['ok' => 1, 'count' => 0]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'bad_action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
