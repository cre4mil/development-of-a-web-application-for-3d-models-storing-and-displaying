<?php
/**
 * @var string $heading
 * @var string $message
 * @var string|null $backUrl
 * @var string|null $backLabel
 */
?>
<div class="page page-narrow">
  <div class="empty">
    <i class="bi bi-emoji-frown"></i>
    <h3><?= e($heading) ?></h3>
    <p><?= e($message) ?></p>
    <a href="<?= e($backUrl ?? 'index.php') ?>" class="btn btn-primary"><?= e($backLabel ?? 'กลับหน้าหลัก') ?></a>
  </div>
</div>
