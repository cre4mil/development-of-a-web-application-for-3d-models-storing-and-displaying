<?php
/**
 * payment.php — Centralized Payment API
 *
 * POST action=create_order  — สร้าง order จากหลายโมเดล (ตะกร้า) + อัปโหลดสลิป
 * GET  action=check_access  — ตรวจสิทธิ์ดาวน์โหลด (ผ่าน order_items)
 * GET  action=my_orders     — ดู orders ของผู้ซื้อ
 * GET  action=admin_orders  — Admin/Seller ดู orders ทั้งหมด
 * POST action=update_status — Admin อนุมัติ/ปฏิเสธ order + อัปเดต Creator Wallet
 */
require 'connect.php';
header('Content-Type: application/json; charset=utf-8');

$uid = (int)($_SESSION['uid'] ?? 0);

// ========== GET Requests ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // แสดงหน้า My Orders UI
    if (empty($action)) {
        if (!$uid) { header('Location: index.php?login=1'); exit; }
        header_remove('Content-Type');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="th">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>คำสั่งซื้อของฉัน — 3D Gallery</title>
          <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
          <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
          <style>
            :root {
              --bg:        #f8f9fa;
              --surface:   #ffffff;
              --surface2:  #f8f9fa;
              --border:    rgba(0,0,0,0.09);
              --accent:    #111111;
              --accent2:   #198754;
              --text:      #212529;
              --muted:     #6c757d;
              --danger:    #dc3545;
            }
            body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
            .btn-back{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:10px;padding:.5rem 1.2rem;font-size:.875rem;font-weight:500;text-decoration:none;transition:.2s;}
            .btn-back:hover{background:var(--surface);color:var(--text);}
            .wrap{max-width:1200px;margin:0 auto;padding:2.5rem 1.5rem 5rem;}
            .page-title{font-size:1.75rem;font-weight:800;margin-bottom:.25rem;text-align:left;}
            .card-panel{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1.75rem;margin-bottom:1.5rem;}
            .order-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;margin-bottom:1rem;overflow:hidden;}
            .order-header{padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);}
            .order-ref{font-family:monospace;font-size:.875rem;background:rgba(17,17,17,.1);color:var(--accent);padding:.25rem .65rem;border-radius:6px;}
            .badge-pending{background:rgba(244,163,10,.15);color:#f4a30a;border-radius:20px;padding:.25rem .75rem;font-size:.75rem;font-weight:600;}
            .badge-approved{background:rgba(25,135,84,.15);color:var(--accent2);border-radius:20px;padding:.25rem .75rem;font-size:.75rem;font-weight:600;}
            .badge-rejected{background:rgba(220,53,69,.15);color:var(--danger);border-radius:20px;padding:.25rem .75rem;font-size:.75rem;font-weight:600;}
            .order-items-list{padding:.75rem 1.25rem;}
            .oi-row{display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border);}
            .oi-row:last-child{border-bottom:none;}
            .oi-thumb{width:42px;height:42px;border-radius:8px;object-fit:cover;background:var(--surface);}
            .oi-info{flex:1;min-width:0;}
            .oi-title{font-size:.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
            .oi-creator{font-size:.7rem;color:var(--muted);}
            .oi-price{font-weight:700;color:var(--accent2);white-space:nowrap;font-size:.875rem;}
            .order-footer{padding:.75rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);background:rgba(0,0,0,.03);}
            .total-label{color:var(--muted);font-size:.8rem;}
            .total-amount{font-weight:800;font-size:1.1rem;color:var(--accent);}
            .download-btn{background:#111;border:none;color:#fff;padding:.4rem 1rem;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;cursor:pointer;transition:.2s;}
            .download-btn:hover{opacity:.9;color:#fff;}
            .empty-state{text-align:center;padding:4rem 1rem;color:var(--muted);}
            .empty-icon{font-size:3.5rem;margin-bottom:1rem;}
          </style>
        </head>
        <body>
        <header class="site-header">
          <div class="container">
            <a href="index.php" class="logo">
              <span class="logo-icon"><i class="bi bi-box"></i></span>
              3D Gallery
            </a>
            <a href="index.php" class="btn-back"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
          </div>
        </header>
        <div class="container py-5">
          <div class="d-flex align-items-center mb-1">
            <h1 class="page-title mb-0"><i class="bi bi-bag me-2" style="color:var(--accent)"></i>คำสั่งซื้อของฉัน</h1>
          </div>
          <p class="text-muted mb-4">ประวัติการสั่งซื้อและสถานะการชำระเงิน</p>
          
          <div class="row">
            <div class="col-12" id="ordersContainer">
              <div class="empty-state"><div class="empty-icon"><i class="bi bi-hourglass-split"></i></div><p>กำลังโหลด...</p></div>
            </div>
          </div>
        </div>
        <script>
        async function loadMyOrders(){
          const res = await fetch('payment.php?action=my_orders');
          const d = await res.json();
          const container = document.getElementById('ordersContainer');
          if(!d.orders || d.orders.length===0){
            container.innerHTML=`<div class="empty-state"><div class="empty-icon"><i class="bi bi-bag-x"></i></div><p>ยังไม่มีประวัติการสั่งซื้อ</p><a href="index.php" class="download-btn mt-2"><i class="bi bi-shop"></i>เริ่มช้อปปิ้ง</a></div>`;
            return;
          }
          container.innerHTML = d.orders.map(o=>{
            const badges = {pending:'badge-pending',approved:'badge-approved',rejected:'badge-rejected'};
            const labels = {pending:'รอตรวจสอบ',approved:'อนุมัติแล้ว',rejected:'ปฏิเสธ'};
            const date = new Date(o.created_at).toLocaleString('th-TH',{dateStyle:'medium',timeStyle:'short'});
            const items = (o.items||[]).map(i=>`
              <div class="oi-row">
                <img src="uploads/${escH(i.model_thumb||'')}" class="oi-thumb" onerror="this.src='https://placehold.co/42x42/22222f/6e6e8a?text=3D'" alt="">
                <div class="oi-info">
                  <div class="oi-title">${escH(i.model_title)}</div>
                  <div class="oi-creator"><i class="bi bi-person me-1"></i>${escH(i.creator_name)}</div>
                </div>
                <div class="oi-price">฿${parseFloat(i.price).toLocaleString('th-TH',{minimumFractionDigits:2})}</div>
                ${o.payment_status==='approved' ? `<a href="download.php?id=${i.model_id}" class="download-btn"><i class="bi bi-download"></i></a>` : ''}
              </div>`).join('');
            return `<div class="order-card">
              <div class="order-header">
                <div>
                  <span class="order-ref">${escH(o.order_ref)}</span>
                  <small style="color:var(--muted);margin-left:.75rem;font-size:.75rem">${date}</small>
                </div>
                <span class="${badges[o.payment_status]||'badge-pending'}">${labels[o.payment_status]||o.payment_status}</span>
              </div>
              <div class="order-items-list">${items}</div>
              <div class="order-footer">
                <div><span class="total-label">ยอดรวม </span><span class="total-amount">฿${parseFloat(o.total_amount).toLocaleString('th-TH',{minimumFractionDigits:2})}</span></div>
                ${o.payment_status==='approved' ? `<span style="color:var(--accent2);font-size:.8rem"><i class="bi bi-check-circle me-1"></i>ปลดล็อกดาวน์โหลดแล้ว</span>` : ''}
              </div>
            </div>`;
          }).join('');
        }
        function escH(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
        loadMyOrders();
        </script>
        </body></html>
        <?php
        exit;
    }

    // ── check_access ──────────────────────────────────────────────────
    if ($action === 'check_access') {
        $modelId = (int)($_GET['model_id'] ?? 0);
        if (!$uid) { echo json_encode(['has_access' => false, 'reason' => 'not_login']); exit; }
        if ($modelId <= 0) { echo json_encode(['has_access' => false, 'reason' => 'bad_id']); exit; }

        $st = $pdo->prepare("SELECT price, user_id FROM models WHERE id = ?");
        $st->execute([$modelId]);
        $model = $st->fetch();
        if (!$model) { echo json_encode(['has_access' => false, 'reason' => 'not_found']); exit; }

        if ($model['price'] <= 0 || (int)$model['user_id'] === $uid) {
            echo json_encode(['has_access' => true, 'reason' => 'free_or_owner']); exit;
        }

        // ตรวจผ่าน order_items + orders
        $chk = $pdo->prepare("
            SELECT o.order_ref FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status = 'approved'
            LIMIT 1
        ");
        $chk->execute([$uid, $modelId]);
        if ($chk->fetch()) {
            echo json_encode(['has_access' => true, 'reason' => 'purchased']); exit;
        }

        // ตรวจ pending
        $pend = $pdo->prepare("
            SELECT o.order_ref FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status = 'pending'
            LIMIT 1
        ");
        $pend->execute([$uid, $modelId]);
        $pendRow = $pend->fetch();
        if ($pendRow) {
            echo json_encode(['has_access' => false, 'reason' => 'pending', 'order_ref' => $pendRow['order_ref']]); exit;
        }

        echo json_encode(['has_access' => false, 'reason' => 'not_purchased', 'price' => (float)$model['price']]); exit;
    }

    // ── my_orders ─────────────────────────────────────────────────────
    if ($action === 'my_orders') {
        if (!$uid) { http_response_code(401); echo json_encode(['error' => 'not_login']); exit; }

        $st = $pdo->prepare("
            SELECT o.id, o.order_ref, o.total_amount, o.payment_status, o.created_at
            FROM orders o
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
        ");
        $st->execute([$uid]);
        $orders = $st->fetchAll();

        // ดึง items ของแต่ละ order
        foreach ($orders as &$order) {
            $items = $pdo->prepare("
                SELECT oi.model_id, oi.price, m.title AS model_title, m.thumb AS model_thumb, u.username AS creator_name
                FROM order_items oi
                JOIN models m ON m.id = oi.model_id
                JOIN users u ON u.id = oi.creator_id
                WHERE oi.order_id = ?
            ");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        unset($order);

        echo json_encode(['orders' => $orders]); exit;
    }

    // ── admin_orders ──────────────────────────────────────────────────
    if ($action === 'admin_orders') {
        if (!$uid) { http_response_code(401); echo json_encode(['error' => 'not_login']); exit; }

        $isAdminSt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $isAdminSt->execute([$uid]);
        $adminFlag = (int)($isAdminSt->fetchColumn() ?? 0);

        $statusFilter = $_GET['status'] ?? '';
        $params = [];
        $where = '1=1';

        if (!$adminFlag) {
            // ไม่ใช่ admin: แสดงเฉพาะ order ที่มีโมเดลของตัวเอง
            $where .= ' AND EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.creator_id = ?)';
            $params[] = $uid;
        }
        if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
            $where .= ' AND o.payment_status = ?';
            $params[] = $statusFilter;
        }

        $st = $pdo->prepare("
            SELECT o.id, o.order_ref, o.total_amount, o.payment_status, o.payment_slip_url,
                   o.admin_note, o.created_at, o.approved_at,
                   buyer.username AS buyer_name, buyer.id AS buyer_id,
                   adm.username  AS admin_name
            FROM orders o
            JOIN users buyer ON buyer.id = o.buyer_id
            LEFT JOIN users adm ON adm.id = o.admin_approved_by
            WHERE $where
            ORDER BY o.created_at DESC
        ");
        $st->execute($params);
        $orders = $st->fetchAll();

        // ดึง items ของแต่ละ order
        foreach ($orders as &$order) {
            $items = $pdo->prepare("
                SELECT oi.model_id, oi.price, oi.platform_fee, oi.creator_earning,
                       m.title AS model_title, m.thumb AS model_thumb,
                       u.username AS creator_name, u.id AS creator_id
                FROM order_items oi
                JOIN models m ON m.id = oi.model_id
                JOIN users u ON u.id = oi.creator_id
                WHERE oi.order_id = ?
            ");
            $items->execute([$order['id']]);
            $order['items'] = $items->fetchAll();
        }
        unset($order);

        // counts
        $countWhere = $adminFlag ? '1=1' : 'EXISTS (SELECT 1 FROM order_items oi3 WHERE oi3.order_id = o.id AND oi3.creator_id = ?)';
        $countParams = $adminFlag ? [] : [$uid];
        $counts = [];
        foreach (['pending', 'approved', 'rejected'] as $s) {
            $cs = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $countWhere AND o.payment_status = ?");
            $cs->execute(array_merge($countParams, [$s]));
            $counts[$s] = (int)$cs->fetchColumn();
        }

        echo json_encode(['orders' => $orders, 'counts' => $counts, 'is_admin' => $adminFlag]); exit;
    }

    http_response_code(400); echo json_encode(['error' => 'bad_action']); exit;
}

// ========== POST Requests ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$uid) { http_response_code(401); echo json_encode(['error' => 'not_login']); exit; }

    $action = $_POST['action'] ?? '';

    // ── create_order (multi-item, centralized) ────────────────────────
    if ($action === 'create_order') {
        $modelIds = array_map('intval', (array)($_POST['model_ids'] ?? []));
        $modelIds = array_filter($modelIds, fn($id) => $id > 0);
        $modelIds = array_unique(array_values($modelIds));

        if (empty($modelIds)) { echo json_encode(['error' => 'no_models']); exit; }

        // อัปโหลดสลิป
        if (empty($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'slip_required']); exit;
        }
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) { echo json_encode(['error' => 'slip_format']); exit; }
        if ($_FILES['slip']['size'] > 5 * 1024 * 1024) { echo json_encode(['error' => 'slip_too_large']); exit; }

        $slipFile = 'slip_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $slipDir  = __DIR__ . '/uploads/slips/';
        if (!is_dir($slipDir)) mkdir($slipDir, 0775, true);
        if (!move_uploaded_file($_FILES['slip']['tmp_name'], $slipDir . $slipFile)) {
            echo json_encode(['error' => 'slip_save_failed']); exit;
        }
        $slipUrl = 'uploads/slips/' . $slipFile;

        // ดึงข้อมูลโมเดลทั้งหมด
        $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
        $st = $pdo->prepare("SELECT id, user_id, price, title FROM models WHERE id IN ($placeholders) AND is_public = 1 AND price > 0");
        $st->execute($modelIds);
        $models = $st->fetchAll(PDO::FETCH_ASSOC);
        $modelMap = array_column($models, null, 'id');

        // ตรวจสอบแต่ละโมเดล
        $validItems = [];
        // ดึงค่าธรรมเนียมจาก DB (ตั้งค่าโดย Admin) fallback ไปที่ค่า default ใน connect.php
        $feeRow = $pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key='platform_fee_pct'")->fetchColumn();
        $feePct = ($feeRow !== false ? (float)$feeRow : PLATFORM_FEE_PCT) / 100;
        foreach ($modelIds as $mid) {
            if (!isset($modelMap[$mid])) continue;
            $m = $modelMap[$mid];
            if ((int)$m['user_id'] === $uid) continue; // ข้ามโมเดลตัวเอง

            // ตรวจว่าซื้อแล้วหรือยัง
            $chk = $pdo->prepare("
                SELECT 1 FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.buyer_id = ? AND oi.model_id = ? AND o.payment_status IN ('approved','pending')
                LIMIT 1
            ");
            $chk->execute([$uid, $mid]);
            if ($chk->fetch()) continue; // ข้ามโมเดลที่มี order pending/approved แล้ว

            $price   = (float)$m['price'];
            $fee     = round($price * $feePct, 2);
            $earning = round($price - $fee, 2);

            $validItems[] = [
                'model_id'        => $mid,
                'creator_id'      => (int)$m['user_id'],
                'price'           => $price,
                'platform_fee'    => $fee,
                'creator_earning' => $earning,
            ];
        }

        if (empty($validItems)) {
            echo json_encode(['error' => 'no_valid_items']); exit;
        }

        $total     = array_sum(array_column($validItems, 'price'));
        $orderRef  = strtoupper(bin2hex(random_bytes(6)));

        // --- SlipOK API Integration ---
        $paymentStatus = 'pending';
        $adminNote = null;
        
        if (defined('SLIPOK_API_KEY') && SLIPOK_API_KEY !== '') {
            $apiUrl = 'https://api.slipok.com/api/line/apikey/' . SLIPOK_API_KEY;
            $ch = curl_init();
            $cfile = new CURLFile($slipDir . $slipFile, mime_content_type($slipDir . $slipFile), $slipFile);
            
            $postData = ['files' => $cfile];
            $headers = [];
            if (defined('SLIPOK_BRANCH_ID') && SLIPOK_BRANCH_ID !== '') {
                $headers[] = 'x-authorization: ' . SLIPOK_BRANCH_ID;
            }
            
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $apiResult = curl_exec($ch);
            curl_close($ch);
            
            if ($apiResult !== false) {
                $json = json_decode($apiResult, true);
                if (isset($json['success']) && $json['success'] === true && isset($json['data'])) {
                    $apiAmount = (float)($json['data']['amount'] ?? 0);
                    if ($apiAmount == $total) {
                        $paymentStatus = 'approved';
                        $adminNote = 'อนุมัติอัตโนมัติ (SlipOK API)';
                    } else {
                        $paymentStatus = 'pending';
                        $adminNote = "API: ยอดเงินไม่ตรง (โอน $apiAmount / สั่งซื้อ $total)";
                    }
                } else {
                    // API returned false (Fake, duplicate, etc.)
                    // User explicitly requested to REJECT fake/duplicate slips automatically
                    @unlink($slipDir . $slipFile); // delete fake slip
                    echo json_encode(['error' => 'slip_api_rejected', 'detail' => 'สลิปนี้ไม่ถูกต้อง หรือถูกใช้งานไปแล้ว (ตรวจสอบโดย SlipOK)']);
                    exit;
                }
            } else {
                $adminNote = "API Error: ไม่สามารถเชื่อมต่อระบบตรวจสลิปได้";
            }
        }
        // ------------------------------

        $pdo->beginTransaction();
        try {
            // Insert order
            $ins = $pdo->prepare("
                INSERT INTO orders (order_ref, buyer_id, total_amount, payment_slip_url, payment_status, admin_note, approved_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $approvedAt = ($paymentStatus === 'approved') ? date('Y-m-d H:i:s') : null;
            $ins->execute([$orderRef, $uid, $total, $slipUrl, $paymentStatus, $adminNote, $approvedAt]);
            $orderId = (int)$pdo->lastInsertId();

            // Insert order_items
            $insItem = $pdo->prepare("
                INSERT INTO order_items (order_id, model_id, creator_id, price, platform_fee, creator_earning)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($validItems as $item) {
                $insItem->execute([
                    $orderId,
                    $item['model_id'],
                    $item['creator_id'],
                    $item['price'],
                    $item['platform_fee'],
                    $item['creator_earning'],
                ]);
            }

            // ถ้า Approve จาก API อัตโนมัติ ให้อัปเดตเงินเข้า Wallet Creator เลย
            if ($paymentStatus === 'approved') {
                $upsertWallet = $pdo->prepare("
                    INSERT INTO creator_wallets (creator_id, available_balance, total_earned)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        available_balance = available_balance + VALUES(available_balance),
                        total_earned      = total_earned      + VALUES(total_earned)
                ");
                $earningsGroup = [];
                foreach ($validItems as $item) {
                    $cid = $item['creator_id'];
                    $earn = $item['creator_earning'];
                    if(!isset($earningsGroup[$cid])) $earningsGroup[$cid] = 0;
                    $earningsGroup[$cid] += $earn;
                }
                foreach($earningsGroup as $cid => $earn) {
                    $upsertWallet->execute([$cid, $earn, $earn]);
                }
            }

            // ล้างตะกร้า
            $_SESSION['cart'] = [];

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            // ลบไฟล์สลิปที่อัปโหลดไปแล้ว
            @unlink($slipDir . $slipFile);
            echo json_encode(['error' => 'db_error', 'detail' => $e->getMessage()]); exit;
        }

        echo json_encode([
            'ok'        => 1,
            'order_ref' => $orderRef,
            'items'     => count($validItems),
            'total'     => $total,
            'message'   => 'สร้างคำสั่งซื้อเรียบร้อย กรุณารอ Admin ตรวจสอบสลิป'
        ]); exit;
    }

    // ── update_status (Admin Approve/Reject + Wallet update) ─────────
    if ($action === 'update_status') {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $note      = trim($_POST['note'] ?? '');

        if ($orderId <= 0) { echo json_encode(['error' => 'bad_order_id']); exit; }
        if (!in_array($newStatus, ['approved', 'rejected'])) { echo json_encode(['error' => 'bad_status']); exit; }

        // ตรวจสิทธิ์ Admin
        $chkAdm = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $chkAdm->execute([$uid]);
        $isAdmin = (bool)$chkAdm->fetchColumn();
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }

        // ดึง order
        $ord = $pdo->prepare("SELECT id, payment_status FROM orders WHERE id = ?");
        $ord->execute([$orderId]);
        $order = $ord->fetch();
        if (!$order) { echo json_encode(['error' => 'order_not_found']); exit; }
        if ($order['payment_status'] !== 'pending') { echo json_encode(['error' => 'already_processed']); exit; }

        $pdo->beginTransaction();
        try {
            // อัปเดต order status
            $upd = $pdo->prepare("
                UPDATE orders
                SET payment_status = ?, admin_note = ?, admin_approved_by = ?,
                    approved_at = ?
                WHERE id = ?
            ");
            $upd->execute([
                $newStatus,
                $note ?: null,
                $uid,
                $newStatus === 'approved' ? date('Y-m-d H:i:s') : null,
                $orderId,
            ]);

            // ถ้า Approve → บวก creator_earning เข้า wallet ของแต่ละ Creator
            if ($newStatus === 'approved') {
                $items = $pdo->prepare("
                    SELECT creator_id, SUM(creator_earning) AS earn
                    FROM order_items
                    WHERE order_id = ?
                    GROUP BY creator_id
                ");
                $items->execute([$orderId]);
                $creatorEarnings = $items->fetchAll();

                $upsertWallet = $pdo->prepare("
                    INSERT INTO creator_wallets (creator_id, available_balance, total_earned)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        available_balance = available_balance + VALUES(available_balance),
                        total_earned      = total_earned      + VALUES(total_earned)
                ");
                foreach ($creatorEarnings as $ce) {
                    $upsertWallet->execute([$ce['creator_id'], $ce['earn'], $ce['earn']]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'db_error', 'detail' => $e->getMessage()]); exit;
        }

        echo json_encode(['ok' => 1, 'status' => $newStatus]); exit;
    }

    http_response_code(400); echo json_encode(['error' => 'bad_action']); exit;
}

http_response_code(405); echo json_encode(['error' => 'method_not_allowed']);
