<?php
/**
 * @var list<array<string, mixed>> $models
 * @var array{id: int, name: string, admin: bool} $user
 */
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1>โมเดลที่ชอบ</h1>
      <p>ทั้งหมด <?= count($models) ?> รายการ</p>
    </div>
  </div>

  <?php if ($models) : ?>
    <div class="model-grid" id="model-grid">
      <?php foreach ($models as $m) : ?>
        <?= partial('model_card', ['m' => $m, 'user' => $user]) ?>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="empty">
      <i class="bi bi-heart"></i>
      <h3>ยังไม่มีโมเดลที่ถูกใจ</h3>
      <p class="mb-0">กดรูปหัวใจบนโมเดลที่ชอบ แล้วจะมาแสดงที่นี่ · <a href="index.php">เลือกดูโมเดล</a></p>
    </div>
  <?php endif; ?>
</div>

<?= partial('quickview_modal', ['user' => $user]) ?>
