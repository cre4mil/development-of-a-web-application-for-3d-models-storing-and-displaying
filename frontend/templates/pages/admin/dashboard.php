<?php
/**
 * @var array{users: int, models: int, pending: int, revenue: float, ready: int} $stats
 * @var list<array<string, mixed>> $orders
 * @var list<array<string, mixed>> $users
 * @var list<array<string, mixed>> $models
 * @var array{id: int, name: string, admin: bool} $user
 */
use App\Support\Format;

$labels = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธ'];
?>
<div class="page">
  <div class="page-head">
    <div>
      <div class="eyebrow">Admin</div>
      <h1>System Overview</h1>
      <p>สวัสดี <?= e($user['name']) ?> — ภาพรวมระบบและการจัดการ</p>
    </div>
  </div>
  <?= partial('admin_nav', ['section' => 'dashboard']) ?>

  <div class="stat-grid">
    <div class="stat"><span class="stat-icon"><i class="bi bi-people"></i></span><div><div class="num"><?= number_format($stats['users']) ?></div><div class="lbl">ผู้ใช้งานทั้งหมด</div></div></div>
    <div class="stat"><span class="stat-icon violet"><i class="bi bi-box"></i></span><div><div class="num"><?= number_format($stats['models']) ?></div><div class="lbl">โมเดลทั้งหมด</div></div></div>
    <a class="stat" href="admin_orders.php?status=pending"><span class="stat-icon amber"><i class="bi bi-hourglass-split"></i></span><div><div class="num"><?= number_format($stats['pending']) ?></div><div class="lbl">รอตรวจสอบสลิป</div></div></a>
    <div class="stat"><span class="stat-icon green"><i class="bi bi-cash-coin"></i></span><div><div class="num"><?= e(money($stats['revenue'])) ?></div><div class="lbl">ยอดขายรวม</div></div></div>
    <a class="stat" href="admin_payout.php"><span class="stat-icon red"><i class="bi bi-wallet2"></i></span><div><div class="num"><?= number_format($stats['ready']) ?></div><div class="lbl">Creator พร้อมรับ Payout</div></div></a>
  </div>

  <ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-orders" type="button" role="tab"><i class="bi bi-receipt me-1"></i>รายการสั่งซื้อ</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab"><i class="bi bi-people me-1"></i>ผู้ใช้งาน</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-models" type="button" role="tab"><i class="bi bi-boxes me-1"></i>โมเดล</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-orders" role="tabpanel">
      <div class="panel"><div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>รหัส</th><th>วันที่</th><th>ผู้ซื้อ</th><th>โมเดล</th><th>ยอดรวม</th><th>สลิป</th><th>สถานะ</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $order) : ?>
              <tr>
                <td><span class="order-ref"><?= e($order['order_ref']) ?></span></td>
                <td class="text-muted small text-nowrap"><?= e(Format::dateTime($order['created_at'])) ?></td>
                <td><?= e($order['buyer']) ?></td>
                <td><?= e($order['model_title'] ?? '—') ?><?php if ((int) $order['item_count'] > 1) : ?> <span class="badge text-bg-light">+<?= (int) $order['item_count'] - 1 ?></span><?php endif; ?></td>
                <td class="fw-semibold"><?= e(money($order['total_amount'])) ?></td>
                <td><?php if ($order['payment_slip_url']) : ?><a class="btn btn-soft btn-sm" href="<?= e($order['payment_slip_url']) ?>" target="_blank" rel="noopener noreferrer">ดูสลิป</a><?php else : ?>-<?php endif; ?></td>
                <td><span class="status <?= e($order['payment_status']) ?>"><?= e($labels[$order['payment_status']] ?? $order['payment_status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$orders) : ?><tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีรายการสั่งซื้อ</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="panel-body border-top"><a class="btn btn-dark btn-sm" href="admin_orders.php"><i class="bi bi-receipt-cutoff me-1"></i>จัดการ Orders (Approve / Reject)</a></div></div>
    </div>

    <div class="tab-pane fade" id="tab-users" role="tabpanel">
      <div class="panel"><div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>ชื่อผู้ใช้</th><th>อีเมล</th><th>สิทธิ์</th><th class="text-end">จัดการ</th></tr></thead>
          <tbody>
            <?php foreach ($users as $row) : ?>
              <?php $self = (int) $row['id'] === $user['id']; ?>
              <tr data-row="user-<?= (int) $row['id'] ?>">
                <td class="text-muted"><?= (int) $row['id'] ?></td>
                <td class="fw-semibold"><?= e($row['username']) ?><?php if ($self) : ?> <span class="badge text-bg-primary ms-1">คุณ</span><?php endif; ?></td>
                <td><?= e($row['email']) ?></td>
                <td><span class="status <?= (int) $row['is_admin'] === 1 ? 'admin' : 'user' ?>"><?= (int) $row['is_admin'] === 1 ? 'Admin' : 'User' ?></span></td>
                <td class="text-end"><?php if (!$self) : ?><button type="button" class="btn btn-soft btn-sm text-danger" data-admin-action="delete_user" data-id="<?= (int) $row['id'] ?>" data-confirm="ลบผู้ใช้ “<?= e($row['username']) ?>” และโมเดลทั้งหมดของผู้ใช้นี้?"><i class="bi bi-trash3 me-1"></i>ลบ</button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>

    <div class="tab-pane fade" id="tab-models" role="tabpanel">
      <div class="panel"><div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>ID</th><th>โมเดล</th><th>ผู้อัปโหลด</th><th>ราคา</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead>
          <tbody>
            <?php foreach ($models as $row) : ?>
              <tr data-row="model-<?= (int) $row['id'] ?>">
                <td class="text-muted"><?= (int) $row['id'] ?></td>
                <td><a class="fw-semibold" href="model.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener noreferrer"><?= e($row['title']) ?></a></td>
                <td><?= e($row['uploader']) ?></td>
                <td><?= (float) $row['price'] > 0 ? e(money($row['price'])) : 'ฟรี' ?></td>
                <td><span class="status <?= (int) $row['is_public'] === 1 ? 'public' : 'hidden' ?>"><?= (int) $row['is_public'] === 1 ? 'แสดง' : 'ซ่อน' ?></span></td>
                <td class="text-end"><button type="button" class="btn btn-soft btn-sm text-danger" data-admin-action="delete_model" data-id="<?= (int) $row['id'] ?>" data-confirm="ลบโมเดล “<?= e($row['title']) ?>” ถาวร?"><i class="bi bi-trash3 me-1"></i>ลบ</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
</div>
