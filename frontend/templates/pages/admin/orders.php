<?php
/**
 * @var string $status
 * @var list<array<string, mixed>> $orders
 * @var array{pending: int, approved: int, rejected: int} $counts
 */
use App\Support\Format;

$labels = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธ'];
$tabs = ['' => 'ทั้งหมด'] + $labels;
?>
<div class="page">
  <div class="page-head">
    <div>
      <div class="eyebrow">Admin</div>
      <h1>จัดการ Orders</h1>
      <p>ตรวจสลิปและอนุมัติการชำระเงิน</p>
    </div>
  </div>
  <?= partial('admin_nav', ['section' => 'orders']) ?>

  <div class="stat-grid">
    <div class="stat"><span class="stat-icon amber"><i class="bi bi-hourglass-split"></i></span><div><div class="num"><?= $counts['pending'] ?></div><div class="lbl">รอตรวจสอบ</div></div></div>
    <div class="stat"><span class="stat-icon green"><i class="bi bi-check-circle"></i></span><div><div class="num"><?= $counts['approved'] ?></div><div class="lbl">อนุมัติแล้ว</div></div></div>
    <div class="stat"><span class="stat-icon red"><i class="bi bi-x-circle"></i></span><div><div class="num"><?= $counts['rejected'] ?></div><div class="lbl">ปฏิเสธ</div></div></div>
  </div>

  <div class="filter-tabs">
    <?php foreach ($tabs as $value => $label) : ?>
      <a href="admin_orders.php<?= $value === '' ? '' : '?status=' . e($value) ?>" class="<?= $status === $value ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="panel"><div class="table-responsive">
    <table class="table table-hover">
      <thead><tr><th>Order</th><th>รายการโมเดล</th><th>ผู้ซื้อ</th><th>ยอดรวม</th><th>สลิป</th><th>สถานะ</th><th>วันที่</th><th class="text-end">จัดการ</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $order) : ?>
          <tr>
            <td><span class="order-ref"><?= e($order['order_ref']) ?></span></td>
            <td>
              <?php foreach ($order['items'] as $item) : ?>
                <div class="cell-model mb-1">
                  <img class="thumb-sm" src="<?= e(uploadUrl($item['model_thumb'])) ?>" alt="" loading="lazy">
                  <div><div class="t small"><?= e($item['model_title']) ?></div><div class="text-muted small">Creator: <?= e($item['creator_name']) ?> · <?= e(money($item['price'])) ?></div></div>
                </div>
              <?php endforeach; ?>
            </td>
            <td><?= e($order['buyer_name']) ?></td>
            <td class="fw-bold"><?= e(money($order['total_amount'])) ?></td>
            <td><?php if ($order['payment_slip_url']) : ?><button type="button" class="btn btn-soft btn-sm" data-admin-action="view-slip" data-src="<?= e($order['payment_slip_url']) ?>"><i class="bi bi-image"></i></button><?php else : ?>-<?php endif; ?></td>
            <td>
              <span class="status <?= e($order['payment_status']) ?>"><?= e($labels[$order['payment_status']] ?? $order['payment_status']) ?></span>
              <?php if ($order['admin_note']) : ?><div class="text-muted small mt-1"><?= e($order['admin_note']) ?></div><?php endif; ?>
            </td>
            <td class="text-muted small text-nowrap"><?= e(Format::dateTime($order['created_at'])) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($order['payment_status'] === 'pending') : ?>
                <button type="button" class="btn btn-success btn-sm btn-icon" data-admin-action="order-approve" data-id="<?= (int) $order['id'] ?>" title="อนุมัติ" aria-label="อนุมัติ"><i class="bi bi-check-lg"></i></button>
                <button type="button" class="btn btn-danger btn-sm btn-icon" data-admin-action="order-reject" data-id="<?= (int) $order['id'] ?>" title="ปฏิเสธ" aria-label="ปฏิเสธ"><i class="bi bi-x-lg"></i></button>
              <?php else : ?>-<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders) : ?><tr><td colspan="8" class="text-center text-muted py-4">ไม่มี orders</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div></div>
</div>

<div class="modal fade" id="slipModal" tabindex="-1" aria-labelledby="slipTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="slipTitle"><i class="bi bi-receipt me-2"></i>สลิปการโอน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body text-center"><img id="slipImage" class="img-fluid rounded" alt="สลิปการโอน"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="rejectTitle">ปฏิเสธคำสั่งซื้อ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body">
        <label class="form-label" for="rejectNote">เหตุผล (ไม่บังคับ)</label>
        <textarea id="rejectNote" class="form-control" rows="3"></textarea>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button><button type="button" class="btn btn-danger" id="rejectConfirm">ปฏิเสธ</button></div>
    </div>
  </div>
</div>
