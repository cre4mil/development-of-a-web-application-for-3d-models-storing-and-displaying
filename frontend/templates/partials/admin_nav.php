<?php
/** @var string $section active admin section */
$items = [
    'dashboard' => ['admin.php', 'bi-grid-1x2', 'ภาพรวม'],
    'orders' => ['admin_orders.php', 'bi-receipt-cutoff', 'Orders'],
    'payouts' => ['admin_payout.php', 'bi-cash-stack', 'Payout'],
];
?>
<div class="filter-tabs" aria-label="เมนูผู้ดูแลระบบ">
  <?php foreach ($items as $key => [$href, $icon, $label]) : ?>
    <a href="<?= e($href) ?>" class="<?= $key === $section ? 'active' : '' ?>"><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
