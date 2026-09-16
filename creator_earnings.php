<?php
/**
 * creator_earnings.php — Creator Earnings & Payout Dashboard
 */
require 'connect.php';
if (!$uid = (int)($_SESSION['uid'] ?? 0)) { header('Location: login.php'); exit; }

$uname = htmlspecialchars($_SESSION['uname'] ?? '', ENT_QUOTES);

// ── Handle bank account save ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bank') {
    $bankName    = trim($_POST['bank_name'] ?? '');
    $bankAccNo   = trim($_POST['bank_account_no'] ?? '');
    $bankAccName = trim($_POST['bank_account_name'] ?? '');

    $pdo->prepare("
        INSERT INTO creator_wallets (creator_id, bank_name, bank_account_no, bank_account_name)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            bank_name         = VALUES(bank_name),
            bank_account_no   = VALUES(bank_account_no),
            bank_account_name = VALUES(bank_account_name)
    ")->execute([$uid, $bankName, $bankAccNo, $bankAccName]);

    header('Location: creator_earnings.php?saved=1');
    exit;
}

// ── Fetch wallet ──────────────────────────────────────────────────────
$wSt = $pdo->prepare("SELECT * FROM creator_wallets WHERE creator_id = ?");
$wSt->execute([$uid]);
$wallet = $wSt->fetch() ?: [
    'available_balance' => 0,
    'total_earned'      => 0,
    'pending_payout'    => 0,
    'bank_name'         => '',
    'bank_account_no'   => '',
    'bank_account_name' => '',
];

// ── Sales history (per model per order) ──────────────────────────────
$salesSt = $pdo->prepare("
    SELECT oi.model_id, oi.price, oi.platform_fee, oi.creator_earning,
           oi.created_at, m.title AS model_title, m.thumb AS model_thumb,
           u.username AS buyer_name, o.order_ref, o.payment_status
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN models m ON m.id = oi.model_id
    JOIN users  u ON u.id = o.buyer_id
    WHERE oi.creator_id = ?
    ORDER BY oi.created_at DESC
    LIMIT 100
");
$salesSt->execute([$uid]);
$sales = $salesSt->fetchAll();

// ── Monthly earnings (last 6 months) ─────────────────────────────────
$monthlySt = $pdo->prepare("
    SELECT DATE_FORMAT(oi.created_at, '%Y-%m') AS month,
           SUM(oi.creator_earning)             AS earning,
           COUNT(*)                            AS sales
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.creator_id = ? AND o.payment_status = 'approved'
      AND oi.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthlySt->execute([$uid]);
$monthly = $monthlySt->fetchAll();

// ── Payout history ────────────────────────────────────────────────────
$payoutSt = $pdo->prepare("
    SELECT pr.*, adm.username AS admin_name
    FROM payout_requests pr
    LEFT JOIN users adm ON adm.id = pr.admin_id
    WHERE pr.creator_id = ?
    ORDER BY pr.created_at DESC
");
$payoutSt->execute([$uid]);
$payoutHistory = $payoutSt->fetchAll();

// ── Summary stats ─────────────────────────────────────────────────────
$totalModels     = $pdo->prepare("SELECT COUNT(DISTINCT model_id) FROM order_items WHERE creator_id=?");
$totalModels->execute([$uid]);
$totalModelsSold = $totalModels->fetchColumn();

$totalOrders = count($sales);
$threshold   = PAYOUT_THRESHOLD;
$feePct      = PLATFORM_FEE_PCT;

$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>รายได้ของฉัน — 3D Gallery</title>
  <meta name="description" content="ดูรายได้ ประวัติการขาย และประวัติการรับเงินสำหรับ Creator">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
    }
    *{box-sizing:border-box;}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

    /* ── Header ── */
    .site-header{background:#111;color:#fff;padding:1rem 0;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,0.3);}
    .site-header .container{display:flex;justify-content:space-between;align-items:center;max-width:1200px;margin:0 auto;padding:0 1.5rem;}
    .logo{font-size:1.2rem;font-weight:800;text-decoration:none;color:#fff;display:flex;align-items:center;gap:.4rem;}
    .logo .logo-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .logo .logo-icon i{color:#fff;font-size:1.4rem;}
    .nav-link-c{color:rgba(255,255,255,0.7);text-decoration:none;font-size:.875rem;padding:.4rem .8rem;border-radius:8px;transition:.2s;display:inline-flex;align-items:center;gap:.3rem;}
    .nav-link-c:hover{background:rgba(255,255,255,0.12);color:#fff;}
    .nav-link-c.active{background:rgba(255,255,255,0.18);color:#fff;font-weight:600;}
    .nav-link-danger{color:rgba(255,120,120,0.85) !important;}

    .wrap{max-width:1100px;margin:0 auto;padding:2rem 1.5rem 5rem;}
    .page-title{font-size:1.75rem;font-weight:800;margin-bottom:.25rem;}

    /* Wallet Hero */
    .wallet-hero{background:#fff;border:1px solid var(--border);border-radius:20px;padding:2rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:2rem;align-items:center;justify-content:space-between;box-shadow:0 2px 12px rgba(0,0,0,0.07);}
    .wallet-balance-label{color:var(--muted);font-size:.8rem;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.5px;}
    .wallet-balance-amount{font-size:2.5rem;font-weight:800;color:#111;line-height:1;}
    .wallet-sub{color:#d97706;font-size:.8rem;margin-top:.35rem;font-weight:600;}
    .wallet-stats{display:flex;gap:2rem;flex-wrap:wrap;}
    .wallet-stat{text-align:center;}
    .wallet-stat-num{font-size:1.2rem;font-weight:700;color:#111;}
    .wallet-stat-label{color:var(--muted);font-size:.7rem;margin-top:.1rem;}
    .wallet-threshold-bar{width:100%;height:6px;background:#e8e8e8;border-radius:99px;margin-top:.75rem;overflow:hidden;}
    .wallet-threshold-fill{height:100%;background:#111;border-radius:99px;transition:width 1s ease;}
    .threshold-label{font-size:.72rem;color:var(--muted);margin-top:.35rem;}

    /* Stats grid */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;}
    @media(max-width:640px){.stats-row{grid-template-columns:1fr 1fr;}}
    .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1.25rem;}
    .stat-icon-sm{font-size:1.25rem;margin-bottom:.5rem;}
    .stat-num-sm{font-size:1.3rem;font-weight:800;}
    .stat-label-sm{color:var(--muted);font-size:.75rem;margin-top:.15rem;}

    /* Tabs */
    .tab-nav{display:flex;gap:.25rem;border-bottom:1px solid var(--border);margin-bottom:0;}
    .tab-btn{background:transparent;border:none;color:var(--muted);font-weight:500;font-size:.875rem;padding:.75rem 1.25rem;cursor:pointer;transition:.2s;border-bottom:2px solid transparent;margin-bottom:-1px;}
    .tab-btn:hover{color:#111;}
    .tab-btn.active{color:#7c3aed;border-bottom-color:#7c3aed;}
    .tab-content{display:none;}
    .tab-content.active{display:block;}

    .card-panel{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden;margin-bottom:1.5rem;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
    .card-panel-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);}
    .card-panel-title{font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:.5rem;color:#111;}
    .card-panel-title i{color:#7c3aed;}

    .data-table{width:100%;border-collapse:collapse;}
    .data-table th{background:#f8f8fa;color:#666;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;padding:.9rem 1.25rem;border-bottom:1px solid var(--border);white-space:nowrap;}
    .data-table td{padding:.875rem 1.25rem;border-bottom:1px solid var(--border);font-size:.875rem;vertical-align:middle;color:#111;}
    .data-table tr:last-child td{border-bottom:none;}
    .data-table tr:hover td{background:#fafafa;}

    .badge-ap{background:#dcfce7;color:#16a34a;border-radius:20px;padding:.2rem .65rem;font-size:.7rem;font-weight:600;}
    .badge-pe{background:#fef3c7;color:#d97706;border-radius:20px;padding:.2rem .65rem;font-size:.7rem;font-weight:600;}
    .badge-tr{background:#dcfce7;color:#16a34a;border-radius:20px;padding:.2rem .65rem;font-size:.7rem;font-weight:600;}
    .badge-rj{background:#fee2e2;color:#dc2626;border-radius:20px;padding:.2rem .65rem;font-size:.7rem;font-weight:600;}

    /* model thumb */
    .model-thumb-sm{width:38px;height:38px;border-radius:8px;object-fit:cover;background:var(--surface2);}
    .model-cell{display:flex;align-items:center;gap:.75rem;}

    /* chart */
    .chart-wrap{padding:1.5rem;}

    /* Form */
    .form-label-c{font-size:.8rem;font-weight:600;color:#555;margin-bottom:.4rem;display:block;}
    .form-input-c{width:100%;background:#f8f8f8;border:1px solid rgba(0,0,0,0.12);border-radius:10px;padding:.7rem 1rem;color:#111;font-size:.875rem;outline:none;transition:.2s;margin-bottom:1rem;}
    .form-input-c:focus{border-color:#111;box-shadow:0 0 0 3px rgba(0,0,0,0.08);}
    .btn-save{background:#111;border:none;color:#fff;font-weight:700;font-size:.875rem;padding:.75rem 2rem;border-radius:12px;cursor:pointer;transition:.2s;}
    .btn-save:hover{background:#333;transform:translateY(-1px);}
    .alert-saved{background:#dcfce7;border:1px solid #86efac;color:#16a34a;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;font-weight:600;}

    .btn-slip-link{background:#ede9fe;border:1px solid #c4b5fd;color:#7c3aed;padding:.3rem .65rem;border-radius:6px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;font-weight:600;}
  </style>
</head>
<body>

<header class="site-header">
  <div class="container">
    <a href="index.php" class="logo">
      <span class="logo-icon"><i class="bi bi-box"></i></span>
      3D Gallery
    </a>
    <nav style="display:flex;gap:.25rem;align-items:center;">
      <a href="index.php"            class="nav-link-c"><i class="bi bi-house-fill"></i> หน้าหลัก</a>
      <a href="profile.php"          class="nav-link-c"><i class="bi bi-person-fill"></i> โปรไฟล์</a>
      <a href="creator_earnings.php" class="nav-link-c active"><i class="bi bi-cash-stack"></i> รายได้</a>
      <a href="logout.php"           class="nav-link-c nav-link-danger"><i class="bi bi-box-arrow-right"></i></a>
    </nav>
  </div>
</header>

<div class="wrap">
  <h1 class="page-title"><i class="bi bi-cash-stack me-2" style="color:var(--accent)"></i>รายได้ของฉัน</h1>
  <p style="color:var(--muted);margin-bottom:2rem;">ยินดีต้อนรับกลับ, <strong style="color:var(--text)"><?= $uname ?></strong> · ค่าธรรมเนียมแพลตฟอร์ม <?= $feePct ?>%</p>

  <!-- Wallet Hero -->
  <div class="wallet-hero">
    <div>
      <div class="wallet-balance-label"><i class="bi bi-wallet2 me-1"></i>ยอดเงินคงเหลือ (พร้อมถอน)</div>
      <div class="wallet-balance-amount">฿<?= number_format($wallet['available_balance'], 2) ?></div>
      <?php if ($wallet['pending_payout'] > 0): ?>
      <div class="wallet-sub"><i class="bi bi-clock me-1"></i>รอโอน: ฿<?= number_format($wallet['pending_payout'], 2) ?></div>
      <?php endif; ?>
      <?php
        $progress = $threshold > 0 ? min(100, ($wallet['available_balance'] / $threshold) * 100) : 100;
        $remaining = max(0, $threshold - $wallet['available_balance']);
      ?>
      <div class="wallet-threshold-bar">
        <div class="wallet-threshold-fill" style="width:<?= $progress ?>%"></div>
      </div>
      <div class="threshold-label">
        <?php if ($wallet['available_balance'] >= $threshold): ?>
          <i class="bi bi-check-circle-fill text-success me-1"></i>ยอดถึงเกณฑ์แล้ว! แอดมินจะโอนให้ในรอบถัดไป
        <?php else: ?>
          ต้องการอีก ฿<?= number_format($remaining, 2) ?> เพื่อถึงเกณฑ์ขั้นต่ำ (฿<?= number_format($threshold) ?>)
        <?php endif; ?>
      </div>
    </div>
    <div class="wallet-stats">
      <div class="wallet-stat">
        <div class="wallet-stat-num">฿<?= number_format($wallet['total_earned'], 2) ?></div>
        <div class="wallet-stat-label">รายได้รวมทั้งหมด</div>
      </div>
      <div class="wallet-stat">
        <div class="wallet-stat-num"><?= $totalModelsSold ?></div>
        <div class="wallet-stat-label">โมเดลที่ขายได้</div>
      </div>
      <div class="wallet-stat">
        <div class="wallet-stat-num"><?= $totalOrders ?></div>
        <div class="wallet-stat-label">รายการขาย</div>
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="card-panel">
    <div class="tab-nav" style="padding:0 1.5rem;">
      <button class="tab-btn active" data-tab="tab-chart">📈 กราฟรายได้</button>
      <button class="tab-btn" data-tab="tab-sales">📋 ประวัติการขาย</button>
      <button class="tab-btn" data-tab="tab-payouts">💸 ประวัติรับเงิน</button>
      <button class="tab-btn" data-tab="tab-bank">🏦 บัญชีธนาคาร</button>
    </div>

    <!-- Tab: Chart -->
    <div class="tab-content active" id="tab-chart">
      <div class="chart-wrap">
        <?php if (empty($monthly)): ?>
          <div style="text-align:center;padding:3rem;color:var(--muted)">
            <i class="bi bi-bar-chart" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
            ยังไม่มีข้อมูลรายได้ที่ approved
          </div>
        <?php else: ?>
          <canvas id="earningsChart" style="max-height:320px"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tab: Sales -->
    <div class="tab-content" id="tab-sales">
      <?php if (empty($sales)): ?>
        <div style="text-align:center;padding:3rem;color:var(--muted)">
          <i class="bi bi-bag-x" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
          ยังไม่มีประวัติการขาย
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>โมเดล</th>
              <th>Order Ref</th>
              <th>ผู้ซื้อ</th>
              <th>ราคา</th>
              <th>ค่าธรรมเนียม</th>
              <th>รายได้สุทธิ</th>
              <th>สถานะ</th>
              <th>วันที่</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sales as $s): ?>
            <tr>
              <td>
                <div class="model-cell">
                  <img src="uploads/<?= htmlspecialchars($s['model_thumb'] ?? '') ?>"
                       class="model-thumb-sm"
                       onerror="this.src='https://placehold.co/38x38/22222f/6e6e8a?text=3D'" alt="">
                  <span style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($s['model_title']) ?></span>
                </div>
              </td>
              <td><span style="font-family:monospace;font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($s['order_ref']) ?></span></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($s['buyer_name']) ?></td>
              <td>฿<?= number_format($s['price'], 2) ?></td>
              <td style="color:var(--danger)">-฿<?= number_format($s['platform_fee'], 2) ?></td>
              <td style="font-weight:700;color:var(--accent2)">฿<?= number_format($s['creator_earning'], 2) ?></td>
              <td>
                <?php if ($s['payment_status'] === 'approved'): ?>
                  <span class="badge-ap">อนุมัติ</span>
                <?php else: ?>
                  <span class="badge-pe">รอตรวจ</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:.75rem"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Payouts -->
    <div class="tab-content" id="tab-payouts">
      <?php if (empty($payoutHistory)): ?>
        <div style="text-align:center;padding:3rem;color:var(--muted)">
          <i class="bi bi-cash" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
          ยังไม่มีประวัติการรับเงิน
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>ยอดโอน</th>
              <th>สถานะ</th>
              <th>สร้างเมื่อ</th>
              <th>โอนเมื่อ</th>
              <th>สลิปจาก Admin</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payoutHistory as $p): ?>
            <tr>
              <td style="font-family:monospace;color:var(--muted)">#<?= $p['id'] ?></td>
              <td style="font-weight:700;color:var(--accent2)">฿<?= number_format($p['amount'], 2) ?></td>
              <td>
                <?php if ($p['status']==='transferred'): ?>
                  <span class="badge-tr"><i class="bi bi-check-circle me-1"></i>โอนแล้ว</span>
                <?php elseif ($p['status']==='pending'): ?>
                  <span class="badge-pe"><i class="bi bi-clock me-1"></i>รอโอน</span>
                <?php else: ?>
                  <span class="badge-rj">ปฏิเสธ</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:.8rem"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
              <td style="color:var(--muted);font-size:.8rem">
                <?= $p['transferred_at'] ? date('d/m/Y H:i', strtotime($p['transferred_at'])) : '-' ?>
              </td>
              <td>
                <?php if ($p['admin_transfer_slip']): ?>
                  <a href="<?= htmlspecialchars($p['admin_transfer_slip']) ?>" target="_blank" class="btn-slip-link">
                    <i class="bi bi-image"></i>ดูสลิป
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted)">-</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:.8rem">
                <?= $p['admin_note'] ? htmlspecialchars($p['admin_note']) : '-' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Bank -->
    <div class="tab-content" id="tab-bank">
      <div style="padding:1.75rem;max-width:480px;">
        <?php if ($saved): ?>
          <div class="alert-saved"><i class="bi bi-check-circle-fill"></i>บันทึกข้อมูลธนาคารเรียบร้อยแล้ว</div>
        <?php endif; ?>
        <p style="color:var(--muted);font-size:.875rem;margin-bottom:1.5rem">
          ข้อมูลนี้ใช้สำหรับให้ Admin โอนเงินให้คุณ กรุณากรอกให้ถูกต้อง
        </p>
        <form method="POST" action="creator_earnings.php">
          <input type="hidden" name="action" value="save_bank">
          <label class="form-label-c">ธนาคาร</label>
          <input type="text" name="bank_name" class="form-input-c"
                 placeholder="เช่น กสิกรไทย, กรุงไทย, SCB"
                 value="<?= htmlspecialchars($wallet['bank_name'] ?? '') ?>">

          <label class="form-label-c">เลขบัญชี</label>
          <input type="text" name="bank_account_no" class="form-input-c"
                 placeholder="เช่น 1234567890"
                 value="<?= htmlspecialchars($wallet['bank_account_no'] ?? '') ?>">

          <label class="form-label-c">ชื่อเจ้าของบัญชี</label>
          <input type="text" name="bank_account_name" class="form-input-c"
                 placeholder="ชื่อ-นามสกุล ตามบัตร"
                 value="<?= htmlspecialchars($wallet['bank_account_name'] ?? '') ?>">

          <button type="submit" class="btn-save"><i class="bi bi-save me-1"></i>บันทึกข้อมูล</button>
        </form>
      </div>
    </div>

  </div><!-- end card-panel -->

</div><!-- end wrap -->

<script>
// ─── Tabs ─────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

// ─── Chart ───────────────────────────────────────────────
<?php if (!empty($monthly)): ?>
const monthlyData = <?= json_encode($monthly) ?>;
const labels  = monthlyData.map(m => {
  const [y, mo] = m.month.split('-');
  return new Date(y, mo-1).toLocaleDateString('th-TH', {month:'short', year:'2-digit'});
});
const earnings = monthlyData.map(m => parseFloat(m.earning));
const sales    = monthlyData.map(m => parseInt(m.sales));

const ctx = document.getElementById('earningsChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels,
    datasets: [
      {
        label: 'รายได้ (฿)',
        data: earnings,
        backgroundColor: 'rgba(124,106,247,0.6)',
        borderColor: '#7c6af7',
        borderWidth: 2,
        borderRadius: 8,
        yAxisID: 'y',
      },
      {
        label: 'จำนวนรายการ',
        data: sales,
        type: 'line',
        borderColor: '#2cb67d',
        backgroundColor: 'rgba(44,182,125,0.1)',
        borderWidth: 2,
        pointBackgroundColor: '#2cb67d',
        pointRadius: 5,
        tension: 0.4,
        fill: true,
        yAxisID: 'y1',
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode:'index', intersect:false },
    plugins: {
      legend: { labels: { color: '#e8e8f0', font: { family:'Inter' } } },
      tooltip: {
        callbacks: {
          label: ctx => ctx.datasetIndex === 0
            ? ` ฿${ctx.parsed.y.toLocaleString('th-TH', {minimumFractionDigits:2})}`
            : ` ${ctx.parsed.y} รายการ`
        }
      }
    },
    scales: {
      x: { ticks: { color:'#6e6e8a' }, grid: { color:'rgba(255,255,255,.05)' } },
      y:  { position:'left',  ticks:{ color:'#6e6e8a', callback:v=>'฿'+v.toLocaleString() }, grid:{color:'rgba(255,255,255,.05)'} },
      y1: { position:'right', ticks:{ color:'#2cb67d' }, grid:{ drawOnChartArea:false } },
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>
