<?php
/**
 * admin_payout.php — Payout Management Dashboard (Admin Only)
 *
 * POST action=create_payout   — สร้าง payout request + หัก wallet
 * POST action=transfer_payout — อัปโหลดสลิป + เปลี่ยนสถานะเป็น transferred
 * POST action=reject_payout   — ปฏิเสธและคืน balance
 */
require 'connect.php';
if (!$uid = (int)($_SESSION['uid'] ?? 0)) { header('Location: login.php'); exit; }

// Admin only
$adm = $pdo->prepare("SELECT is_admin FROM users WHERE id=?");
$adm->execute([$uid]);
if (!(bool)$adm->fetchColumn()) { header('Location: index.php'); exit; }

// ── Handle POST actions ───────────────────────────────────────────────
$msg = $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_payout') {
        $creatorId = (int)($_POST['creator_id'] ?? 0);
        $amount    = (float)($_POST['amount'] ?? 0);

        if ($creatorId <= 0 || $amount <= 0) {
            echo json_encode(['error' => 'bad_params']); exit;
        }

        // ตรวจ wallet
        $wSt = $pdo->prepare("SELECT available_balance FROM creator_wallets WHERE creator_id = ?");
        $wSt->execute([$creatorId]);
        $wallet = $wSt->fetch();
        if (!$wallet || $wallet['available_balance'] < $amount) {
            echo json_encode(['error' => 'insufficient_balance']); exit;
        }

        $pdo->beginTransaction();
        try {
            // หัก balance และเพิ่ม pending_payout
            $pdo->prepare("
                UPDATE creator_wallets
                SET available_balance = available_balance - ?,
                    pending_payout    = pending_payout + ?
                WHERE creator_id = ?
            ")->execute([$amount, $amount, $creatorId]);

            // สร้าง payout request
            $pdo->prepare("
                INSERT INTO payout_requests (creator_id, amount, status, admin_id)
                VALUES (?, ?, 'pending', ?)
            ")->execute([$creatorId, $amount, $uid]);

            $pdo->commit();
            echo json_encode(['ok' => 1, 'payout_id' => $pdo->lastInsertId()]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'db_error']);
        }
        exit;
    }

    if ($action === 'transfer_payout') {
        $payoutId = (int)($_POST['payout_id'] ?? 0);
        if ($payoutId <= 0) { echo json_encode(['error' => 'bad_id']); exit; }

        // ดึง payout
        $pr = $pdo->prepare("SELECT * FROM payout_requests WHERE id = ?");
        $pr->execute([$payoutId]);
        $payout = $pr->fetch();
        if (!$payout || $payout['status'] !== 'pending') {
            echo json_encode(['error' => 'not_found_or_done']); exit;
        }

        // อัปโหลดสลิป
        $slipPath = null;
        if (!empty($_FILES['slip']) && $_FILES['slip']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) { echo json_encode(['error' => 'slip_format']); exit; }
            $fname = 'payout_slip_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dir   = __DIR__ . '/uploads/payout_slips/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            if (!move_uploaded_file($_FILES['slip']['tmp_name'], $dir . $fname)) {
                echo json_encode(['error' => 'slip_save_failed']); exit;
            }
            $slipPath = 'uploads/payout_slips/' . $fname;
        } else {
            echo json_encode(['error' => 'slip_required']); exit;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE payout_requests
                SET status='transferred', admin_transfer_slip=?, transferred_at=NOW(), admin_id=?
                WHERE id=?
            ")->execute([$slipPath, $uid, $payoutId]);

            // หัก pending_payout
            $pdo->prepare("
                UPDATE creator_wallets
                SET pending_payout = pending_payout - ?
                WHERE creator_id = ?
            ")->execute([$payout['amount'], $payout['creator_id']]);

            $pdo->commit();
            echo json_encode(['ok' => 1]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'db_error']);
        }
        exit;
    }

    if ($action === 'reject_payout') {
        $payoutId = (int)($_POST['payout_id'] ?? 0);
        $note     = trim($_POST['note'] ?? '');
        if ($payoutId <= 0) { echo json_encode(['error' => 'bad_id']); exit; }

        $pr = $pdo->prepare("SELECT * FROM payout_requests WHERE id = ? AND status='pending'");
        $pr->execute([$payoutId]);
        $payout = $pr->fetch();
        if (!$payout) { echo json_encode(['error' => 'not_found_or_done']); exit; }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE payout_requests SET status='rejected', admin_note=? WHERE id=?
            ")->execute([$note, $payoutId]);

            // คืน balance
            $pdo->prepare("
                UPDATE creator_wallets
                SET available_balance = available_balance + ?,
                    pending_payout    = pending_payout - ?
                WHERE creator_id = ?
            ")->execute([$payout['amount'], $payout['amount'], $payout['creator_id']]);

            $pdo->commit();
            echo json_encode(['ok' => 1]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'db_error']);
        }
        exit;
    }
    if ($action === 'update_platform_fee') {
        $newFee = (float)($_POST['fee_pct'] ?? -1);
        if ($newFee < 0 || $newFee > 100) {
            echo json_encode(['error' => 'bad_value']); exit;
        }
        try {
            $pdo->prepare("
                INSERT INTO platform_settings (setting_key, setting_value, updated_by)
                VALUES ('platform_fee_pct', ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
            ")->execute([(string)$newFee, $uid]);
            echo json_encode(['ok' => 1, 'fee_pct' => $newFee]);
        } catch (Throwable $e) {
            echo json_encode(['error' => 'db_error']);
        }
        exit;
    }
    exit;
}

// ── Fetch Settings from DB (override connect.php constants if saved) ──────────
$feeRow = $pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_fee_pct'")->fetchColumn();
$currentFeePct = ($feeRow !== false) ? (float)$feeRow : PLATFORM_FEE_PCT;

// ── Fetch Data ───────────────────────────────────────────────────────
$threshold = PAYOUT_THRESHOLD;

// Wallets ที่ถึงเกณฑ์
$wallets = $pdo->query("
    SELECT cw.*, u.username, u.email
    FROM creator_wallets cw
    JOIN users u ON u.id = cw.creator_id
    ORDER BY cw.available_balance DESC
")->fetchAll();

// Payout requests ล่าสุด
$payouts = $pdo->query("
    SELECT pr.*, u.username AS creator_name, u.email AS creator_email
    FROM payout_requests pr
    JOIN users u ON u.id = pr.creator_id
    ORDER BY pr.created_at DESC
    LIMIT 100
")->fetchAll();

// Stats
$totalPending   = $pdo->query("SELECT COUNT(*) FROM payout_requests WHERE status='pending'")->fetchColumn();
$totalPaid      = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payout_requests WHERE status='transferred'")->fetchColumn();
$readyCreators  = $pdo->query("SELECT COUNT(*) FROM creator_wallets WHERE available_balance >= {$threshold}")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payout Management — 3D Gallery Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root{
      --bg:        #f4f4f6;
      --surface:   #ffffff;
      --surface2:  #f0f0f2;
      --border:    rgba(0,0,0,0.09);
      --accent:    #111111;
      --accent2:   #333333;
      --text:      #111111;
      --muted:     #777777;
      --danger:    #d93025;
      --warn:      #f59e0b;
      --blue:      #2563eb;
    }
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
    /* ── Header (same as global site header) ── */
    .site-header{background:#111;color:#fff;padding:1rem 0;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,0.3);}
    .site-header .container{display:flex;justify-content:space-between;align-items:center;max-width:1200px;margin:0 auto;padding:0 1.5rem;}
    .logo{font-size:1.2rem;font-weight:800;text-decoration:none;color:#fff;display:flex;align-items:center;gap:.4rem;}
    .logo .logo-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .logo .logo-icon i{color:#fff;font-size:1.4rem;}
    .site-header nav{display:flex;gap:.25rem;align-items:center;}
    .nav-link-c{color:rgba(255,255,255,0.7);text-decoration:none;font-size:.875rem;padding:.4rem .8rem;border-radius:8px;transition:.2s;display:inline-flex;align-items:center;gap:.3rem;}
    .nav-link-c:hover{background:rgba(255,255,255,0.12);color:#fff;}
    .nav-link-c.active{background:rgba(255,255,255,0.18);color:#fff;font-weight:600;}
    .nav-link-danger{color:rgba(255,120,120,0.85) !important;}

    /* wrap */
    .wrap{max-width:1100px;margin:0 auto;padding:2rem 1.5rem 5rem;}
    .page-title{font-size:1.75rem;font-weight:800;margin-bottom:.25rem;}

    /* stats */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;}
    @media(max-width:640px){.stats-row{grid-template-columns:1fr;}}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.5rem;display:flex;align-items:center;gap:1.25rem;box-shadow:0 2px 8px rgba(0,0,0,0.06);}
    .stat-icon{font-size:2rem;width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .stat-icon.purple{background:#ede9fe;color:#7c3aed;}
    .stat-icon.green{background:#dcfce7;color:#16a34a;}
    .stat-icon.warn{background:#fef3c7;color:#d97706;}
    .stat-num{font-size:1.6rem;font-weight:800;line-height:1;}
    .stat-label{color:var(--muted);font-size:.8rem;margin-top:.2rem;}

    .card-panel{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden;margin-bottom:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
    .card-panel-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
    .card-panel-title{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.5rem;color:#111;}
    .card-panel-title i{color:#7c3aed;}

    /* table */
    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{background:#f8f8fa;color:#666;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;padding:1rem 1.25rem;border-bottom:1px solid var(--border);white-space:nowrap;}
    .data-table td{padding:.9rem 1.25rem;border-bottom:1px solid var(--border);font-size:.875rem;vertical-align:middle;color:#111;}
    .data-table tr:last-child td{border-bottom:none;}
    .data-table tr:hover td{background:#fafafa;}

    .badge-p{background:#ede9fe;color:#7c3aed;border-radius:20px;padding:.25rem .75rem;font-size:.72rem;font-weight:600;}
    .badge-t{background:#dcfce7;color:#16a34a;border-radius:20px;padding:.25rem .75rem;font-size:.72rem;font-weight:600;}
    .badge-r{background:#fee2e2;color:#dc2626;border-radius:20px;padding:.25rem .75rem;font-size:.72rem;font-weight:600;}
    .badge-ready{background:#fef3c7;color:#d97706;border-radius:20px;padding:.2rem .6rem;font-size:.7rem;font-weight:600;}

    .btn-pay{background:#111;border:none;color:#fff;padding:.5rem 1.1rem;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:.35rem;letter-spacing:.2px;}
    .btn-pay:hover{background:#333;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
    .btn-pay:disabled{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none;}
    .btn-reject{background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;padding:.45rem .85rem;border-radius:8px;font-size:.8rem;cursor:pointer;font-weight:600;transition:.2s;}
    .btn-reject:hover{background:#fecaca;}
    .btn-slip-view{background:#ede9fe;border:1px solid #c4b5fd;color:#7c3aed;padding:.35rem .75rem;border-radius:8px;font-size:.8rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;font-weight:600;}

    .creator-avatar{width:34px;height:34px;border-radius:50%;background:#111;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#fff;flex-shrink:0;}
    .creator-name{font-weight:600;font-size:.875rem;color:#111;}
    .creator-email{color:var(--muted);font-size:.72rem;}

    /* balance */
    .balance-amount{font-weight:800;font-size:1rem;color:#111;}
    .balance-pending{font-size:.75rem;color:#d97706;font-weight:500;}
    .balance-empty{color:var(--muted);font-size:.875rem;}

    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
    .modal-overlay.show{display:flex;}
    .modal-box{background:#fff;border:1px solid var(--border);border-radius:20px;padding:2rem;width:100%;max-width:460px;margin:1rem;box-shadow:0 20px 60px rgba(0,0,0,0.15);}
    .modal-title{font-weight:700;font-size:1.1rem;margin-bottom:1.25rem;color:#111;}
    .form-label-c{font-size:.8rem;font-weight:600;color:#555;margin-bottom:.4rem;display:block;}
    .form-input-c{width:100%;background:#f8f8f8;border:1px solid rgba(0,0,0,0.12);border-radius:10px;padding:.7rem 1rem;color:#111;font-size:.875rem;outline:none;transition:.2s;}
    .form-input-c:focus{border-color:#111;box-shadow:0 0 0 3px rgba(0,0,0,0.08);}
    .slip-drop{border:2px dashed rgba(0,0,0,0.15);border-radius:12px;padding:1.5rem;text-align:center;cursor:pointer;transition:.2s;position:relative;background:#fafafa;}
    .slip-drop:hover{border-color:#111;background:#f5f5f5;}
    .slip-drop input{position:absolute;inset:0;opacity:0;cursor:pointer;}
    .slip-preview-modal{display:none;margin-top:.75rem;border-radius:8px;overflow:hidden;}
    .slip-preview-modal img{width:100%;border-radius:8px;}
    .modal-btns{display:flex;gap:.75rem;margin-top:1.5rem;}
    .btn-modal-submit{flex:1;background:#111;border:none;color:#fff;padding:.8rem;border-radius:12px;font-weight:700;cursor:pointer;transition:.2s;font-size:.9rem;}
    .btn-modal-submit:hover{background:#333;transform:translateY(-1px);}
    .btn-modal-cancel{flex:0 0 auto;background:#f0f0f0;border:1px solid rgba(0,0,0,0.1);color:#555;padding:.8rem 1.2rem;border-radius:12px;cursor:pointer;font-weight:600;}

    /* tabs */
    .tab-btns{display:flex;gap:.5rem;margin-bottom:0;}
    .tab-btn{background:transparent;border:none;color:var(--muted);font-weight:500;font-size:.875rem;padding:.6rem 1.25rem;border-radius:10px 10px 0 0;cursor:pointer;transition:.2s;}
    .tab-btn.active{background:var(--surface);color:var(--text);border:1px solid var(--border);border-bottom:1px solid var(--surface);}
  </style>
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="admin.php" class="logo">
      <span class="logo-icon"><i class="bi bi-box"></i></span>
      3D Gallery
      <span class="logo-label">ADMIN</span>
    </a>
    <nav>
      <a href="admin.php"       class="nav-link-c"><i class="bi bi-grid-fill"></i> Dashboard</a>
      <a href="admin_orders.php" class="nav-link-c"><i class="bi bi-receipt"></i> Orders</a>
      <a href="admin_payout.php" class="nav-link-c active"><i class="bi bi-cash-stack"></i> Payout</a>
      <a href="index.php"       class="nav-link-c"><i class="bi bi-house-door"></i> หน้าหลัก</a>
      <a href="logout.php"      class="nav-link-c nav-link-danger"><i class="bi bi-box-arrow-right"></i></a>
    </nav>
  </div>
</header>

<div class="wrap">
  <h1 class="page-title"><i class="bi bi-cash-stack me-2" style="color:var(--accent)"></i>Payout Management</h1>
  <p style="color:var(--muted);margin-bottom:2rem;">จัดการการโอนเงินให้ Creator · เกณฑ์ขั้นต่ำ ฿<?= number_format($threshold) ?></p>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon warn"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-num"><?= (int)$totalPending ?></div>
        <div class="stat-label">รอโอนเงิน</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="stat-num">฿<?= number_format($totalPaid, 0) ?></div>
        <div class="stat-label">โอนแล้วทั้งหมด</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon purple"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-num"><?= (int)$readyCreators ?></div>
        <div class="stat-label">Creator ถึงเกณฑ์</div>
      </div>
    </div>
  </div>

  <!-- ── Platform Fee Settings ── -->
  <div class="card-panel mb-4" style="border-left: 4px">
    <div class="card-panel-header" style="padding-bottom:.5rem;">
      <div class="card-panel-title"><i class="bi bi-sliders me-2" style="color:#2563eb;"></i>ตั้งค่าค่าธรรมเนียม</div>
    </div>
    <div style="padding:1rem 1.25rem 0.25rem;">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <span style="color:var(--muted);font-size:.875rem;">ค่าธรรมเนียมปัจจุบัน:</span>
          <span id="currentFeeDisplay" style="font-size:1.8rem;font-weight:800;color:#2563eb;line-height:1;"><?= number_format($currentFeePct, 1) ?><span style="font-size:1rem;font-weight:600;color:#2563eb;">%</span></span>
          <span style="font-size:.78rem;color:var(--muted);">(Creator ได้รับ <?= number_format(100 - $currentFeePct, 1) ?>%)</span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <label for="newFeeInput" style="font-size:.875rem;font-weight:600;color:var(--text);white-space:nowrap;">ปรับเป็น:</label>
          <div class="d-flex align-items-center gap-1" style="background:#f4f4f6;border:1.5px solid rgba(0,0,0,.12);border-radius:10px;padding:.30rem .75rem;">
            <input type="number" id="newFeeInput" min="0" max="100" step="0.5" value="<?= $currentFeePct ?>"
              style="width:50px;border:none;background:transparent;font-size:1rem;font-weight:600;color:var(--text);outline:none;text-align:center;">
            <span style="font-weight:700;color:var(--muted);">%</span>
          </div>
          <button id="btnUpdateFee" onclick="updatePlatformFee()"
            style="background:#2563eb;color:#fff;border:none;border-radius:10px;padding:.45rem 1.25rem;font-weight:700;font-size:.875rem;cursor:pointer;display:flex;align-items:center;gap:.4rem;transition:.2s;"
            onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
            <i class="bi bi-check-circle-fill"></i> บันทึก
          </button>
        </div>
      </div>
      <div style="margin-top:.75rem;padding:.6rem .9rem;background:#eff6ff;border-radius:8px;font-size:.8rem;color:#1e40af;">
        <i class="bi bi-info-circle-fill me-1"></i>
        ค่าธรรมเนียมนี้จะมีผลกับคำสั่งซื้อ <strong>ใหม่</strong> เท่านั้น คำสั่งซื้อเก่าที่สร้างไปแล้วจะไม่ถูกเปลี่ยนแปลง
      </div>
      <div id="feeUpdateAlert" class="d-none mt-2" style="padding:.6rem .9rem;border-radius:8px;font-size:.85rem;font-weight:600;"></div>
    </div>
  </div>

  <!-- Creator Wallets -->
  <div class="card-panel">
    <div class="card-panel-header">
      <div class="card-panel-title"><i class="bi bi-wallet2"></i>กระเป๋าเงิน Creator</div>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Creator</th>
            <th>ยอดคงเหลือ</th>
            <th>รอโอน</th>
            <th>รายได้รวม</th>
            <th>บัญชีธนาคาร</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($wallets)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">ยังไม่มีข้อมูล Wallet</td></tr>
          <?php else: ?>
          <?php foreach ($wallets as $w): ?>
          <tr>
            <td>
              <div class="creator-cell">
                <div class="creator-avatar"><?= strtoupper(substr($w['username'],0,1)) ?></div>
                <div>
                  <div class="creator-name"><?= htmlspecialchars($w['username']) ?></div>
                  <div class="creator-email"><?= htmlspecialchars($w['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php if ($w['available_balance'] > 0): ?>
                <div class="balance-amount">฿<?= number_format($w['available_balance'], 2) ?></div>
              <?php else: ?>
                <span class="balance-empty">฿0.00</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($w['pending_payout'] > 0): ?>
                <span class="balance-pending"><i class="bi bi-clock me-1"></i>฿<?= number_format($w['pending_payout'], 2) ?></span>
              <?php else: ?>
                <span style="color:var(--muted)">-</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.875rem">฿<?= number_format($w['total_earned'], 2) ?></td>
            <td>
              <?php if ($w['bank_account_no']): ?>
                <div style="font-size:.8rem"><?= htmlspecialchars($w['bank_name'] ?? '') ?></div>
                <div style="font-family:monospace;font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($w['bank_account_no']) ?></div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem">ยังไม่ระบุ</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($w['available_balance'] >= $threshold): ?>
                <span class="badge-ready"><i class="bi bi-check-circle me-1"></i>พร้อมโอน</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.75rem">ยอดไม่ถึงเกณฑ์</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($w['available_balance'] > 0): ?>
              <button class="btn-pay"
                onclick="openPayoutModal(<?= $w['creator_id'] ?>, '<?= htmlspecialchars($w['username']) ?>', <?= $w['available_balance'] ?>)"
                <?= $w['available_balance'] < $threshold ? 'title="ยอดไม่ถึงเกณฑ์ แต่สามารถกด Manual ได้"' : '' ?>>
                <i class="bi bi-send"></i>สร้าง Payout
              </button>
              <?php else: ?>
              <span style="color:var(--muted);font-size:.8rem">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Payout Requests -->
  <div class="card-panel">
    <div class="card-panel-header">
      <div class="card-panel-title"><i class="bi bi-list-check"></i>ประวัติ Payout Requests</div>
    </div>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Creator</th>
            <th>ยอด</th>
            <th>สถานะ</th>
            <th>สร้างเมื่อ</th>
            <th>โอนเมื่อ</th>
            <th>สลิป</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payouts)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">ยังไม่มีรายการ Payout</td></tr>
          <?php else: ?>
          <?php foreach ($payouts as $p): ?>
          <tr>
            <td><span style="font-family:monospace;color:var(--muted)">#<?= $p['id'] ?></span></td>
            <td>
              <div class="creator-cell">
                <div class="creator-avatar"><?= strtoupper(substr($p['creator_name'],0,1)) ?></div>
                <div>
                  <div class="creator-name"><?= htmlspecialchars($p['creator_name']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-weight:700;color:var(--accent2)">฿<?= number_format($p['amount'], 2) ?></td>
            <td>
              <?php if ($p['status']==='pending'): ?>
                <span class="badge-p">รอโอน</span>
              <?php elseif ($p['status']==='transferred'): ?>
                <span class="badge-t">โอนแล้ว</span>
              <?php else: ?>
                <span class="badge-r">ปฏิเสธ</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.8rem"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
            <td style="color:var(--muted);font-size:.8rem">
              <?= $p['transferred_at'] ? date('d/m/Y H:i', strtotime($p['transferred_at'])) : '-' ?>
            </td>
            <td>
              <?php if ($p['admin_transfer_slip']): ?>
                <a href="<?= htmlspecialchars($p['admin_transfer_slip']) ?>" target="_blank" class="btn-slip-view">
                  <i class="bi bi-image"></i>ดูสลิป
                </a>
              <?php else: ?>
                <span style="color:var(--muted)">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['status']==='pending'): ?>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                  <button class="btn-pay" onclick="openTransferModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['creator_name']) ?>', <?= $p['amount'] ?>)">
                    <i class="bi bi-upload"></i>แนบสลิป
                  </button>
                  <button class="btn-reject" onclick="rejectPayout(<?= $p['id'] ?>)">ปฏิเสธ</button>
                </div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:.8rem">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- end wrap -->

<!-- Modal: Create Payout -->
<div class="modal-overlay" id="payoutModal">
  <div class="modal-box">
    <div class="modal-title"><i class="bi bi-send me-2" style="color:var(--accent2)"></i>สร้าง Payout</div>
    <input type="hidden" id="payoutCreatorId">
    <div style="margin-bottom:1rem">
      <label class="form-label-c">Creator</label>
      <div id="payoutCreatorName" style="font-weight:600;color:var(--text)"></div>
    </div>
    <div style="margin-bottom:1rem">
      <label class="form-label-c">ยอดคงเหลือ</label>
      <div id="payoutBalance" style="font-size:1.25rem;font-weight:800;color:var(--accent2)"></div>
    </div>
    <div style="margin-bottom:1rem">
      <label class="form-label-c" for="payoutAmount">ยอดที่ต้องการโอน (฿)</label>
      <input type="number" id="payoutAmount" class="form-input-c" step="0.01" min="1" placeholder="ระบุยอดเงิน">
    </div>
    <div class="modal-btns">
      <button class="btn-modal-cancel" onclick="closeAllModals()">ยกเลิก</button>
      <button class="btn-modal-submit" onclick="submitPayout()"><i class="bi bi-send me-1"></i>ยืนยันสร้าง Payout</button>
    </div>
  </div>
</div>

<!-- Modal: Transfer Slip -->
<div class="modal-overlay" id="transferModal">
  <div class="modal-box">
    <div class="modal-title"><i class="bi bi-upload me-2" style="color:var(--accent2)"></i>แนบสลิปการโอน</div>
    <input type="hidden" id="transferPayoutId">
    <div style="margin-bottom:1rem;">
      <label class="form-label-c">Creator</label>
      <div id="transferCreatorName" style="font-weight:600"></div>
    </div>
    <div style="margin-bottom:1rem;">
      <label class="form-label-c">ยอดโอน</label>
      <div id="transferAmount" style="font-weight:800;font-size:1.2rem;color:var(--accent2)"></div>
    </div>
    <label class="form-label-c">สลิปการโอน</label>
    <div class="slip-drop" id="transferDropZone">
      <input type="file" id="transferSlipInput" accept="image/jpeg,image/png,image/webp">
      <i class="bi bi-image" style="font-size:2rem;color:var(--muted);display:block;margin-bottom:.5rem"></i>
      <div style="color:var(--muted);font-size:.85rem">คลิกหรือลากสลิปมาวาง</div>
    </div>
    <div class="slip-preview-modal" id="transferPreview">
      <img id="transferPreviewImg" alt="">
    </div>
    <div class="modal-btns">
      <button class="btn-modal-cancel" onclick="closeAllModals()">ยกเลิก</button>
      <button class="btn-modal-submit" id="btnTransferSubmit" onclick="submitTransfer()"><i class="bi bi-check-circle me-1"></i>ยืนยันโอนแล้ว</button>
    </div>
  </div>
</div>

<script>
// ─── Modal Helpers ────────────────────────────────────────
function closeAllModals(){
  document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('show'));
}
document.querySelectorAll('.modal-overlay').forEach(m=>{
  m.addEventListener('click', e=>{ if(e.target===m) closeAllModals(); });
});

// ─── Create Payout Modal ──────────────────────────────────
function openPayoutModal(creatorId, name, balance){
  document.getElementById('payoutCreatorId').value = creatorId;
  document.getElementById('payoutCreatorName').textContent = name;
  document.getElementById('payoutBalance').textContent = '฿' + balance.toLocaleString('th-TH',{minimumFractionDigits:2});
  document.getElementById('payoutAmount').value = balance.toFixed(2);
  document.getElementById('payoutModal').classList.add('show');
}

async function submitPayout(){
  const creatorId = document.getElementById('payoutCreatorId').value;
  const amount    = parseFloat(document.getElementById('payoutAmount').value);
  if (!amount || amount <= 0){ alert('กรุณาระบุยอดเงิน'); return; }

  const res = await fetch('admin_payout.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'create_payout', creator_id:creatorId, amount:amount.toFixed(2)})
  });
  const d = await res.json();
  if(d.ok){ alert('สร้าง Payout เรียบร้อย'); location.reload(); }
  else alert('เกิดข้อผิดพลาด: ' + (d.error||'unknown'));
}

// ─── Transfer Slip Modal ──────────────────────────────────
function openTransferModal(payoutId, name, amount){
  document.getElementById('transferPayoutId').value = payoutId;
  document.getElementById('transferCreatorName').textContent = name;
  document.getElementById('transferAmount').textContent = '฿' + parseFloat(amount).toLocaleString('th-TH',{minimumFractionDigits:2});
  document.getElementById('transferModal').classList.add('show');
}

const tsInput  = document.getElementById('transferSlipInput');
const tsPreview = document.getElementById('transferPreview');
const tsImg     = document.getElementById('transferPreviewImg');
tsInput.addEventListener('change', ()=>{
  const f = tsInput.files[0];
  if(!f) return;
  const r = new FileReader();
  r.onload=e=>{ tsImg.src=e.target.result; tsPreview.style.display='block'; };
  r.readAsDataURL(f);
});

async function submitTransfer(){
  const payoutId = document.getElementById('transferPayoutId').value;
  if(!tsInput.files[0]){ alert('กรุณาแนบสลิปก่อน'); return; }

  const fd = new FormData();
  fd.append('action','transfer_payout');
  fd.append('payout_id', payoutId);
  fd.append('slip', tsInput.files[0]);

  document.getElementById('btnTransferSubmit').disabled = true;
  const res = await fetch('admin_payout.php', {method:'POST', body:fd});
  const d   = await res.json();
  if(d.ok){ alert('บันทึกการโอนเรียบร้อย!'); location.reload(); }
  else { alert('เกิดข้อผิดพลาด: '+(d.error||'unknown')); document.getElementById('btnTransferSubmit').disabled=false; }
}

// ─── Reject Payout ────────────────────────────────────────
async function rejectPayout(payoutId){
  const note = prompt('เหตุผลที่ปฏิเสธ (ถ้ามี):');
  if(note === null) return;

  const res = await fetch('admin_payout.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'reject_payout', payout_id:payoutId, note:note})
  });
  const d = await res.json();
  if(d.ok){ alert('ปฏิเสธ Payout แล้ว ยอดเงินถูกคืนสู่ Wallet ของ Creator'); location.reload(); }
  else alert('เกิดข้อผิดพลาด');
}

// ─── Update Platform Fee ────────────────────────────────────
async function updatePlatformFee() {
  const input = document.getElementById('newFeeInput');
  const alertEl = document.getElementById('feeUpdateAlert');
  const btn = document.getElementById('btnUpdateFee');
  const val = parseFloat(input.value);

  if (isNaN(val) || val < 0 || val > 100) {
    alertEl.className = 'mt-2';
    alertEl.style.background = '#fee2e2';
    alertEl.style.color = '#b91c1c';
    alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>กรุณาระบุค่าระหว่าง 0–100 เท่านั้น';
    alertEl.classList.remove('d-none');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';

  const res = await fetch('admin_payout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'update_platform_fee', fee_pct: val })
  });
  const d = await res.json();

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> บันทึก';

  if (d.ok) {
    const creatorPct = (100 - d.fee_pct).toFixed(1);
    document.getElementById('currentFeeDisplay').innerHTML =
      d.fee_pct.toFixed(1) + '<span style="font-size:1rem;font-weight:600;color:#2563eb;">%</span>';

    alertEl.style.background = '#dcfce7';
    alertEl.style.color = '#15803d';
    alertEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>บันทึกสำเร็จ! ค่าธรรมเนียมใหม่: <strong>' + d.fee_pct.toFixed(1) + '%</strong> · Creator ได้รับ <strong>' + creatorPct + '%</strong>';
    alertEl.classList.remove('d-none');
    setTimeout(() => alertEl.classList.add('d-none'), 5000);
  } else {
    alertEl.style.background = '#fee2e2';
    alertEl.style.color = '#b91c1c';
    alertEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>เกิดข้อผิดพลาด: ' + (d.error || 'unknown');
    alertEl.classList.remove('d-none');
  }
}
</script>
</body>
</html>
