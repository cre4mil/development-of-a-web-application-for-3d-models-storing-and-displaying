<?php
/**
 * Buyer's order history.
 *
 * @var list<array<string, mixed>> $orders
 */
use App\Support\Format;

$labels = ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ปฏิเสธ'];
?>
<div class="page page-narrow">
  <div class="page-head">
    <div>
      <h1><i class="bi bi-bag me-2 text-primary"></i>คำสั่งซื้อของฉัน</h1>
      <p>ประวัติการสั่งซื้อและสถานะการชำระเงิน</p>
    </div>
  </div>

  <?php foreach ($orders as $order) : ?>
    <?php $approved = $order['payment_status'] === 'approved'; ?>
    <section class="order-card">
      <header>
        <div><span class="order-ref"><?= e($order['order_ref']) ?></span> <span class="text-muted small ms-2"><?= e(Format::dateTime($order['created_at'])) ?></span></div>
        <span class="status <?= e($order['payment_status']) ?>"><?= e($labels[$order['payment_status']] ?? $order['payment_status']) ?></span>
      </header>
      <?php foreach ($order['items'] as $item) : ?>
        <div class="order-item">
          <img class="thumb-sm" src="<?= e(uploadUrl($item['model_thumb'])) ?>" alt="" loading="lazy">
          <div class="grow">
            <a class="fw-semibold text-dark" href="model.php?id=<?= (int) $item['model_id'] ?>"><?= e($item['model_title']) ?></a>
            <div class="small text-muted"><i class="bi bi-person me-1"></i><?= e($item['creator_name']) ?></div>
          </div>
          <div class="fw-bold"><?= e(money($item['price'])) ?></div>
          <?php if ($approved) : ?><a class="btn btn-primary btn-sm btn-icon" href="download.php?id=<?= (int) $item['model_id'] ?>" aria-label="ดาวน์โหลด"><i class="bi bi-download"></i></a><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <footer>
        <div><span class="text-muted small">ยอดรวม</span> <strong class="fs-5"><?= e(money($order['total_amount'])) ?></strong></div>
        <?php if ($approved) : ?>
          <span class="text-success small fw-semibold"><i class="bi bi-check-circle me-1"></i>ปลดล็อกดาวน์โหลดแล้ว</span>
        <?php elseif ($order['admin_note']) : ?>
          <span class="text-muted small"><i class="bi bi-info-circle me-1"></i><?= e($order['admin_note']) ?></span>
        <?php endif; ?>
      </footer>
    </section>
  <?php endforeach; ?>

  <?php if (!$orders) : ?>
    <div class="empty">
      <i class="bi bi-bag-x"></i>
      <h3>ยังไม่มีประวัติการสั่งซื้อ</h3>
      <a href="index.php" class="btn btn-primary mt-2"><i class="bi bi-shop me-1"></i>เลือกดูโมเดล</a>
    </div>
  <?php endif; ?>
</div>
