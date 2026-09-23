<?php
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid || empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}
$isAdmin = true;
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>จัดการ Orders – 3D Gallery</title>
  <link rel="stylesheet" href="style.css?v=<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time() ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
  <style>
    body { background-color: #f8f9fa; color: #212529; }

    .admin-stat-card {
      background: #fff;
      border: 1px solid #eaeaea;
      border-radius: 12px;
      padding: 1.5rem;
      display: flex;
      align-items: center;
      box-shadow: 0 4px 6px rgba(0,0,0,0.02);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .admin-stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    .admin-stat-icon {
      font-size: 2.5rem;
      margin-right: 1.5rem;
    }
    .admin-stat-card.pending .admin-stat-icon { color: #ffc107; }
    .admin-stat-card.approved .admin-stat-icon { color: #198754; }
    .admin-stat-card.rejected .admin-stat-icon { color: #dc3545; }
    .admin-stat-num { font-size: 2rem; font-weight: 700; line-height: 1; margin-bottom: 0.25rem; }
    .admin-stat-label { color: #999; font-size: 0.9rem; font-weight: 500; text-transform: uppercase; }

    .admin-filter-tabs {
      display: flex; gap: 10px; margin-bottom: 1.5rem;
      border-bottom: 1px solid #eaeaea; padding-bottom: 0.5rem;
    }
    .admin-tab {
      background: transparent; border: none; padding: 0.5rem 1rem;
      color: #6c757d; font-weight: 500; border-radius: 8px; transition: 0.2s;
    }
    .admin-tab:hover { background: #e9ecef; }
    .admin-tab.active { background: #111; color: #fff; }

    .admin-orders-table-wrap {
      background: #fff; border-radius: 12px; border: 1px solid #eaeaea;
      padding: 0; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .admin-orders-table { width: 100%; margin: 0; }
    .admin-orders-table th {
      background: #f8f9fa; color: #999; font-weight: 600; font-size: 0.85rem;
      text-transform: uppercase; padding: 1rem; border-bottom: 1px solid #eaeaea;
    }
    .admin-orders-table td {
      padding: 1rem; vertical-align: middle; border-bottom: 1px solid #eaeaea; color: #333;
    }
    .admin-model-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; background: #f0f0f0; }

    .order-ref-badge { background: #e9ecef; padding: 0.25rem 0.5rem; border-radius: 4px; font-family: monospace; font-size: 0.9rem; }

    .order-status-badge { padding: 0.35rem 0.65rem; border-radius: 50rem; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .order-status-pending { background: #fff3cd; color: #856404; }
    .order-status-approved { background: #d4edda; color: #155724; }
    .order-status-rejected { background: #f8d7da; color: #721c24; }

    .footer { background: #111; color: #fff; padding: 20px 0; text-align: center; margin-top: 4rem; }
    .footer p { margin: 0; color: #999; }
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
      <a href="admin_orders.php" class="nav-link-c active"><i class="bi bi-receipt"></i> Orders</a>
      <a href="admin_payout.php" class="nav-link-c"><i class="bi bi-cash-stack"></i> Payout</a>
      <a href="index.php"       class="nav-link-c"><i class="bi bi-house-door"></i> หน้าหลัก</a>
      <a href="logout.php"      class="nav-link-c nav-link-danger"><i class="bi bi-box-arrow-right"></i></a>
    </nav>
  </div>
</header>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2"></i>จัดการ Orders</h3>
      <p class="text-muted small mb-0"><?= $isAdmin ? 'Admin — ดู orders ทั้งหมด' : 'ดู orders ของโมเดลที่คุณเป็นเจ้าของ' ?></p>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="row g-3 mb-4" id="statsCards">
    <div class="col-md-4">
      <div class="admin-stat-card pending">
        <div class="admin-stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="admin-stat-num" id="statPending">0</div>
          <div class="admin-stat-label">รอตรวจสอบ</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="admin-stat-card approved">
        <div class="admin-stat-icon"><i class="bi bi-check-circle"></i></div>
        <div>
          <div class="admin-stat-num" id="statApproved">0</div>
          <div class="admin-stat-label">อนุมัติแล้ว</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="admin-stat-card rejected">
        <div class="admin-stat-icon"><i class="bi bi-x-circle"></i></div>
        <div>
          <div class="admin-stat-num" id="statRejected">0</div>
          <div class="admin-stat-label">ปฏิเสธ</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <div class="admin-filter-tabs mb-3">
    <button class="admin-tab active" data-status="">ทั้งหมด</button>
    <button class="admin-tab" data-status="pending">รอตรวจสอบ</button>
    <button class="admin-tab" data-status="approved">อนุมัติแล้ว</button>
    <button class="admin-tab" data-status="rejected">ปฏิเสธ</button>
  </div>

  <!-- Orders Table -->
  <div class="admin-orders-table-wrap">
    <table class="admin-orders-table">
      <thead>
        <tr>
          <th>รหัส Order</th>
          <th>รายการโมเดล</th>
          <th>ผู้ซื้อ</th>
          <th>ยอดรวม</th>
          <th>สลิป</th>
          <th>สถานะ</th>
          <th>วันที่</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody id="ordersBody">
        <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-hourglass-split me-1"></i>กำลังโหลด...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Slip Viewer Modal -->
<div class="modal fade" id="slipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0">
        <h5 class="modal-title text-white"><i class="bi bi-receipt me-2"></i>สลิปการโอน</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-2">
        <img id="slipModalImg" class="img-fluid rounded" style="max-height:70vh" alt="Slip">
      </div>
    </div>
  </div>
</div>

<footer class="footer"><div class="footer-inner">
  <div class="footer-left"><h4>Admin Panel</h4><p>จัดการคำสั่งซื้อและอนุมัติการชำระเงิน</p></div>
  <div class="footer-right"><h4>© 2026 – 3D Gallery</h4></div>
</div></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
let currentFilter = '';

async function loadOrders(status = '') {
  currentFilter = status;
  const url = 'payment.php?action=admin_orders' + (status ? '&status=' + status : '');
  const res = await fetch(url);
  const d = await res.json();

  // Update stats
  if(d.counts) {
    document.getElementById('statPending').textContent = d.counts.pending || 0;
    document.getElementById('statApproved').textContent = d.counts.approved || 0;
    document.getElementById('statRejected').textContent = d.counts.rejected || 0;
  }

  // Render orders
  const tbody = document.getElementById('ordersBody');
  if (!d.orders || d.orders.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">ไม่มี orders</td></tr>';
    return;
  }

  tbody.innerHTML = d.orders.map(o => {
    const ps = o.payment_status || o.status || 'pending';
    const statusClass = ps === 'approved' ? 'order-status-approved' :
                        ps === 'rejected' ? 'order-status-rejected' : 'order-status-pending';
    const statusText  = ps === 'approved' ? 'อนุมัติแล้ว' :
                        ps === 'rejected' ? 'ปฏิเสธ' : 'รอตรวจสอบ';
    const date = new Date(o.created_at).toLocaleString('th-TH', {dateStyle:'short', timeStyle:'short'});
    const slipUrl = o.payment_slip_url || (o.slip_file ? 'uploads/slips/' + o.slip_file : '');

    // Render order items list
    const items = (o.items || []);
    const itemsHtml = items.length > 0
      ? items.map(item => `
        <div class="d-flex align-items-center gap-2 mb-1">
          <img src="uploads/${escH(item.model_thumb||'')}" width="32" height="32"
               style="border-radius:6px;object-fit:cover;background:#eee"
               onerror="this.src='https://placehold.co/32x32/eee/999?text=3D'" alt="">
          <div style="min-width:0;flex:1">
            <div class="fw-semibold" style="font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">${escH(item.model_title)}</div>
            <div class="text-muted" style="font-size:.68rem">Creator: ${escH(item.creator_name)} · ฿${parseFloat(item.price).toLocaleString('th-TH',{minimumFractionDigits:2})}</div>
          </div>
        </div>`).join('')
      : '<span class="text-muted small">-</span>';

    return `<tr data-id="${o.id}">
      <td><span class="order-ref-badge">${escH(o.order_ref)}</span>
          <div class="text-muted" style="font-size:.65rem;margin-top:.2rem">${items.length} รายการ</div>
      </td>
      <td style="max-width:220px">${itemsHtml}</td>
      <td class="small">${escH(o.buyer_name)}</td>
      <td class="fw-bold">฿${parseFloat(o.total_amount||o.amount||0).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
      <td>${slipUrl ? `<button class="btn btn-sm btn-outline-info view-slip" data-src="${escH(slipUrl)}"><i class="bi bi-image"></i></button>` : '<span class="text-muted small">-</span>'}</td>
      <td><span class="order-status-badge ${statusClass}">${statusText}</span>${o.admin_note ? `<div class="text-muted mt-1" style="font-size:.65rem">${escH(o.admin_note)}</div>` : ''}</td>
      <td class="small text-muted">${date}</td>
      <td>${ps === 'pending' ? `
        <div class="d-flex gap-1">
          <button class="btn btn-sm btn-success btn-approve" data-id="${o.id}" title="อนุมัติ"><i class="bi bi-check-lg"></i></button>
          <button class="btn btn-sm btn-danger btn-reject" data-id="${o.id}" title="ปฏิเสธ"><i class="bi bi-x-lg"></i></button>
        </div>
      ` : '<span class="text-muted small">-</span>'}</td>
    </tr>`;
  }).join('');
}

function escH(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

// Filter tabs
document.querySelectorAll('.admin-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    loadOrders(this.dataset.status);
  });
});

// View slip
document.addEventListener('click', e => {
  const btn = e.target.closest('.view-slip');
  if (!btn) return;
  document.getElementById('slipModalImg').src = btn.dataset.src;
  new bootstrap.Modal(document.getElementById('slipModal')).show();
});

// Approve
document.addEventListener('click', async e => {
  const btn = e.target.closest('.btn-approve');
  if (!btn) return;
  if (!confirm('อนุมัติ order นี้?')) return;
  btn.disabled = true;
  const res = await fetch('payment.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ action: 'update_status', order_id: btn.dataset.id, status: 'approved', note: '', csrf: <?= json_encode($_SESSION['csrf']) ?> })
  });
  const d = await res.json();
  if (d.ok) { loadOrders(currentFilter); }
  else { alert(d.error || 'เกิดข้อผิดพลาด'); btn.disabled = false; }
});

// Reject
document.addEventListener('click', async e => {
  const btn = e.target.closest('.btn-reject');
  if (!btn) return;
  const note = prompt('เหตุผลที่ปฏิเสธ (ถ้ามี):');
  if (note === null) return; // cancelled
  btn.disabled = true;
  const res = await fetch('payment.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ action: 'update_status', order_id: btn.dataset.id, status: 'rejected', note: note, csrf: <?= json_encode($_SESSION['csrf']) ?> })
  });
  const d = await res.json();
  if (d.ok) { loadOrders(currentFilter); }
  else { alert(d.error || 'เกิดข้อผิดพลาด'); btn.disabled = false; }
});

// Initial load
loadOrders();
</script>
</body>
</html>
