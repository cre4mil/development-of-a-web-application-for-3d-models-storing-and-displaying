<?php
require_once 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Check if user is admin
if (empty($_SESSION['uid']) || empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}

$uid = (int)$_SESSION['uid'];

// Fetch Stats
$totalUsers = $pdo->query("SELECT COUNT(id) FROM users")->fetchColumn();
$totalModels = $pdo->query("SELECT COUNT(id) FROM models")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(id) FROM orders WHERE payment_status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status = 'approved'")->fetchColumn() ?? 0;
$pendingPayouts = $pdo->query("SELECT COUNT(*) FROM creator_wallets WHERE available_balance >= ".PAYOUT_THRESHOLD)->fetchColumn();

// Fetch Data for Tabs (Orders now via new structure)
$users = $pdo->query("SELECT id, username, email, is_admin FROM users ORDER BY id DESC")->fetchAll();
$models = $pdo->query("SELECT m.id, m.title, m.is_public, m.price, u.username as uploader FROM models m JOIN users u ON m.user_id = u.id ORDER BY m.id DESC")->fetchAll();
$orders = $pdo->query("
    SELECT o.id, o.order_ref, o.total_amount AS amount, o.payment_slip_url AS slip_file,
           o.payment_status AS status, o.created_at,
           b.username as buyer, m_first.title as model_title,
           (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id=o.id) AS item_count
    FROM orders o
    JOIN users b ON o.buyer_id = b.id
    LEFT JOIN order_items oi ON oi.order_id = o.id AND oi.id = (SELECT MIN(id) FROM order_items WHERE order_id=o.id)
    LEFT JOIN models m_first ON m_first.id = oi.model_id
    ORDER BY o.created_at DESC
")->fetchAll();

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - 3D Gallery</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  <link rel="stylesheet" href="style.css?v=<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time() ?>">
  <style>
    body { background-color: #f8f9fa; color: #212529; }

    .stat-card {
      background: #fff;
      border: 1px solid #eaeaea;
      border-radius: 12px;
      padding: 1.25rem 1rem;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(0,0,0,0.05);
    }
    .stat-card .icon {
      font-size: 2.2rem;
      margin-bottom: 0.5rem;
    }
    .stat-card h3 {
      font-size: 2rem;
      font-weight: 700;
      color: #000;
      margin-bottom: 0.25rem;
    }
    .stat-card p {
      color: #999;
      margin: 0;
      font-size: 0.85rem;
    }

    .nav-tabs {
      border-bottom: 1px solid #eaeaea;
      margin-bottom: 1.5rem;
    }
    .nav-tabs .nav-link {
      color: #999;
      border: none;
      font-weight: 500;
      padding: 1rem 1.5rem;
    }
    .nav-tabs .nav-link.active {
      color: #111;
      background: transparent;
      border-bottom: 3px solid #111;
    }
    .nav-tabs .nav-link:hover:not(.active) {
      color: #666;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      border: 1px solid #eaeaea;
      box-shadow: 0 4px 6px rgba(0,0,0,0.02);
      overflow: hidden;
    }
    .table-custom {
      margin-bottom: 0;
    }
    .table-custom th {
      background: #f8f9fa;
      color: #999;
      font-weight: 600;
      border-bottom: 1px solid #eaeaea;
      font-size: 0.85rem;
      text-transform: uppercase;
      padding: 1rem;
    }
    .table-custom td {
      border-bottom: 1px solid #eaeaea;
      vertical-align: middle;
      padding: 1rem;
      color: #333;
    }
    .badge-status-pending { background: #ffc107; color: #000; }
    .badge-status-approved { background: #198754; color: #fff; }
    .badge-status-rejected { background: #dc3545; color: #fff; }
    .footer { background: #111; color: #fff; padding: 20px 0; text-align: center; }
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
      <a href="admin.php"       class="nav-link-c active"><i class="bi bi-grid-fill"></i> Dashboard</a>
      <a href="admin_orders.php" class="nav-link-c"><i class="bi bi-receipt"></i> Orders</a>
      <a href="admin_payout.php" class="nav-link-c"><i class="bi bi-cash-stack"></i> Payout</a>
      <a href="index.php"       class="nav-link-c"><i class="bi bi-house-door"></i> หน้าหลัก</a>
      <a href="logout.php"      class="nav-link-c nav-link-danger"><i class="bi bi-box-arrow-right"></i></a>
    </nav>
  </div>
</header>

<div class="dashboard-header pt-4 mb-4 pb-3 border-bottom border-secondary-subtle">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-md-end">
    <div>
      <p class="text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">Admin Dashboard</p>
      <h2 class="fw-bolder mb-0 text-dark" style="letter-spacing: -0.5px;"><i class="bi bi-grid-1x2-fill me-2 text-secondary"></i>System Overview</h2>
    </div>
    <div class="mt-3 mt-md-0 text-md-end">
      <div class="text-muted small">ยินดีต้อนรับกลับมา,</div>
      <div class="fw-bold fs-5 text-dark"><i class="bi bi-person-circle me-1 text-secondary"></i> <?= htmlspecialchars($_SESSION['uname']) ?></div>
    </div>
  </div>
</div>

<div class="container mb-5">

  <!-- Stats Row -->
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col">
      <div class="stat-card">
        <div class="icon text-dark"><i class="bi bi-people"></i></div>
        <h3><?= number_format($totalUsers) ?></h3>
        <p>ผู้ใช้งานทั้งหมด</p>
      </div>
    </div>
    <div class="col">
      <div class="stat-card">
        <div class="icon text-dark"><i class="bi bi-box"></i></div>
        <h3><?= number_format($totalModels) ?></h3>
        <p>โมเดลทั้งหมด</p>
      </div>
    </div>
    <div class="col">
      <div class="stat-card">
        <div class="icon" style="color:#ffc107"><i class="bi bi-cart-check"></i></div>
        <h3><?= number_format($pendingOrders) ?></h3>
        <p>รอตรวจสอบสลิป</p>
      </div>
    </div>
    <div class="col">
      <div class="stat-card">
        <div class="icon" style="color:#198754"><i class="bi bi-cash-coin"></i></div>
        <h3>฿<?= number_format($totalRevenue, 2) ?></h3>
        <p>ยอดขายรวม</p>
      </div>
    </div>
    <div class="col">
      <a href="admin_payout.php" style="text-decoration:none; display: block; height: 100%;">
      <div class="stat-card" style="border-color:<?= $pendingPayouts>0?'#7c6af7':'#eaeaea' ?>; height: 100%;">
        <div class="icon" style="color:#7c6af7"><i class="bi bi-cash-stack"></i></div>
        <h3 style="color:<?= $pendingPayouts>0?'#7c6af7':'#000' ?>"><?= number_format($pendingPayouts) ?></h3>
        <p>Creator พร้อมรับ Payout</p>
      </div></a>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
        <i class="bi bi-receipt me-1"></i> รายการสั่งซื้อ
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
        <i class="bi bi-people me-1"></i> จัดการผู้ใช้งาน
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="models-tab" data-bs-toggle="tab" data-bs-target="#models" type="button" role="tab">
        <i class="bi bi-boxes me-1"></i> จัดการโมเดล
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" href="admin_payout.php">
        <i class="bi bi-cash-stack me-1"></i> Payout Management
        <?php if($pendingPayouts>0): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.6rem"><?= $pendingPayouts ?></span><?php endif; ?>
      </a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" href="admin_orders.php">
        <i class="bi bi-receipt-cutoff me-1"></i> หน้าจัดการ Orders
      </a>
    </li>
  </ul>

  <div class="tab-content" id="adminTabsContent">

    <!-- Orders Tab -->
    <div class="tab-pane fade show active" id="orders" role="tabpanel" tabindex="0">
      <div class="card border-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover">
            <thead>
              <tr>
                <th>รหัสคำสั่งซื้อ</th>
                <th>วันที่</th>
                <th>ผู้ซื้อ</th>
                <th>โมเดล (ตัวอย่าง)</th>
                <th>รายการ</th>
                <th>ยอดรวม</th>
                <th>สลิป</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($orders as $o): ?>
              <tr>
                <td><small class="text-muted"><?= htmlspecialchars($o['order_ref']) ?></small></td>
                <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                <td><?= htmlspecialchars($o['buyer']) ?></td>
                <td><?= htmlspecialchars($o['model_title'] ?? '—') ?></td>
                <td><span class="badge bg-secondary"><?= (int)($o['item_count'] ?? 1) ?> รายการ</span></td>
                <td class="text-warning">฿<?= number_format($o['amount'], 2) ?></td>
                <td>
                  <?php if(!empty($o['slip_file'])): ?>
                    <a href="<?= htmlspecialchars($o['slip_file']) ?>" target="_blank" class="btn btn-sm btn-outline-info">ดูสลิป</a>
                  <?php else: ?>
                    -
                  <?php endif ?>
                </td>
                <td>
                  <?php $st = $o['status'] ?? 'pending'; ?>
                  <?php if($st === 'pending'): ?>
                    <span class="badge badge-status-pending">รอตรวจสอบ</span>
                  <?php elseif($st === 'approved'): ?>
                    <span class="badge badge-status-approved">อนุมัติแล้ว</span>
                  <?php elseif($st === 'rejected'): ?>
                    <span class="badge badge-status-rejected">ปฏิเสธ</span>
                  <?php else: ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($st) ?></span>
                  <?php endif ?>
                </td>
                <td>
                  <a href="admin_orders.php" class="btn btn-sm btn-outline-secondary" title="จัดการใน Orders page">
                    <i class="bi bi-arrow-right-circle"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($orders)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">ไม่มีรายการสั่งซื้อ</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="p-3 border-top">
          <a href="admin_orders.php" class="btn btn-sm btn-dark">
            <i class="bi bi-receipt-cutoff me-1"></i>จัดการ Orders ทั้งหมด (Approve / Reject)
          </a>
        </div>
      </div>
    </div>


    <!-- Users Tab -->
    <div class="tab-pane fade" id="users" role="tabpanel" tabindex="0">
      <div class="card border-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>ชื่อผู้ใช้ (Username)</th>
                <th>อีเมล (Email)</th>
                <th>สิทธิ์</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($users as $u): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td>
                  <?= htmlspecialchars($u['username']) ?>
                  <?php if($u['id'] == $uid): ?> <span class="badge bg-primary ms-1">คุณ</span> <?php endif ?>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <?= $u['is_admin'] ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?>
                </td>
                <td>
                  <?php if($u['id'] != $uid): ?>
                    <button class="btn btn-sm btn-outline-danger btn-action" data-action="delete_user" data-id="<?= $u['id'] ?>" data-confirm="แน่ใจหรือไม่ที่จะลบผู้ใช้นี้?"><i class="bi bi-trash"></i> ลบ</button>
                  <?php endif ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Models Tab -->
    <div class="tab-pane fade" id="models" role="tabpanel" tabindex="0">
      <div class="card border-0">
        <div class="table-responsive">
          <table class="table table-custom table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>ชื่อโมเดล</th>
                <th>ผู้อัปโหลด</th>
                <th>ราคา</th>
                <th>สถานะ (สาธารณะ)</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($models as $m): ?>
              <tr>
                <td><?= $m['id'] ?></td>
                <td><a href="model.php?id=<?= $m['id'] ?>" target="_blank" class="text-info text-decoration-none"><?= htmlspecialchars($m['title']) ?></a></td>
                <td><?= htmlspecialchars($m['uploader']) ?></td>
                <td><?= $m['price'] > 0 ? '฿'.number_format($m['price'],2) : 'ฟรี' ?></td>
                <td>
                  <?= $m['is_public'] ? '<span class="badge bg-success">แสดง</span>' : '<span class="badge bg-secondary">ซ่อน</span>' ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-danger btn-action" data-action="delete_model" data-id="<?= $m['id'] ?>" data-confirm="แน่ใจหรือไม่ที่จะลบโมเดลนี้? (หากลบแล้วข้อมูลจะหายไปถาวร)"><i class="bi bi-trash"></i> ลบ</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal loading -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content bg-dark text-white text-center p-3">
      <output class="spinner-border text-warning mx-auto mb-2" aria-label="กำลังประมวลผล"></output>
      <div>กำลังประมวลผล...</div>
    </div>
  </div>
</div>

<footer class="footer mt-auto"><div class="footer-inner">
  <div class="footer-right"><h4>© 2026 - ITDI-Informatics-Burapha University</h4></div>
</div></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
  const csrf = '<?= $_SESSION['csrf'] ?>';
  const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));

  document.querySelectorAll('.btn-action').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      const confirmMsg = btn.dataset.confirm;

      if (confirmMsg && !confirm(confirmMsg)) return;

      loadingModal.show();

      try {
        const formData = new URLSearchParams();
        formData.append('action', action);
        formData.append('id', id);
        formData.append('csrf', csrf);

        const response = await fetch('admin_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: formData
        });

        const data = await response.json();
        loadingModal.hide();

        if (data.ok) {
          alert(data.message || 'ดำเนินการสำเร็จ');
          location.reload();
        } else {
          alert(data.message || 'เกิดข้อผิดพลาด');
        }
      } catch (err) {
        loadingModal.hide();
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
      }
    });
  });
</script>
</body>
</html>
