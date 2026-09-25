<?php
/**
 * Shared page shell.
 *
 * @var string $title
 * @var string $nav      active menu key
 * @var string $content  rendered page body
 * @var list<string> $scripts page-specific files from assets/js
 * @var array{id: int, name: string, admin: bool} $user
 * @var list<array{type: string, message: string, modal: string}> $flash
 * @var string $csrf
 * @var string $next     page to return to after login
 */
$loggedIn = $user['id'] > 0;
$active = static fn (string $key): string => $nav === $key ? ' active' : '';
// Signed-in users get the upload dialog, which renders thumbnails with the viewer library.
$scripts = $loggedIn ? array_values(array_unique(['viewer.js', ...$scripts, 'upload.js'])) : $scripts;
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title><?= e($title) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-auth="<?= $loggedIn ? '1' : '0' ?>" data-page="<?= e($nav) ?>">
<header class="site-header">
  <div class="inner">
    <a href="index.php" class="logo">
      <span class="logo-mark"><i class="bi bi-box"></i></span>
      3D Gallery
    </a>
    <nav class="site-nav" aria-label="เมนูหลัก">
      <?php if ($loggedIn) : ?>
        <?php if ($user['admin']) : ?>
          <a class="nav-link-c<?= $active('admin') ?>" href="admin.php"><i class="bi bi-shield-lock"></i><span class="d-none d-md-inline">Dashboard</span></a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#uploadModal" aria-label="Upload">
          <i class="bi bi-cloud-arrow-up me-sm-1"></i><span class="d-none d-sm-inline">Upload</span>
        </button>
        <div class="dropdown">
          <a href="#" class="user-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar"><?= e(App\Support\Format::initial($user['name'])) ?></span>
            <span class="d-none d-sm-inline"><?= e($user['name']) ?></span>
            <i class="bi bi-chevron-down small text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header">
              <div class="fw-bold text-dark"><?= e($user['name']) ?></div>
              <div class="small text-muted"><?= $user['admin'] ? 'ผู้ดูแลระบบ' : 'เข้าสู่ระบบแล้ว' ?></div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="index.php"><i class="bi bi-house"></i>หน้าหลัก</a></li>
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i>โปรไฟล์</a></li>
            <?php if ($user['admin']) : ?>
              <li><a class="dropdown-item" href="admin.php"><i class="bi bi-shield-lock"></i>แผงควบคุม (Admin)</a></li>
            <?php else : ?>
              <li><a class="dropdown-item" href="like.php"><i class="bi bi-heart"></i>โมเดลที่ชอบ</a></li>
              <li><a class="dropdown-item" href="orders.php"><i class="bi bi-bag"></i>คำสั่งซื้อของฉัน</a></li>
              <li><a class="dropdown-item" href="creator_earnings.php"><i class="bi bi-cash-stack"></i>รายได้</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i>ออกจากระบบ</a></li>
          </ul>
        </div>
      <?php else : ?>
        <button type="button" class="nav-link-c border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">Sign Up</button>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php foreach ($flash as $item) : ?>
  <div hidden data-flash data-type="<?= e($item['type']) ?>" data-modal="<?= e($item['modal']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
<?php if (!$loggedIn && !empty($openLogin)) : ?>
  <div hidden data-flash data-type="success" data-modal="loginModal">กรุณาเข้าสู่ระบบเพื่อใช้งานส่วนนี้</div>
<?php endif; ?>

<main>
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="inner">
    <div><strong>Panisara Kunkam</strong><br>โทร +66949491035 · <a href="mailto:66160026@go.buu.ac.th">66160026@go.buu.ac.th</a></div>
    <div>© 2026 ITDI – Informatics, Burapha University</div>
  </div>
</footer>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<?= partial('confirm_modal') ?>
<?php if ($loggedIn) : ?>
  <?= partial('upload_modal', ['csrf' => $csrf]) ?>
  <?= partial('edit_modal', ['csrf' => $csrf]) ?>
<?php else : ?>
  <?= partial('auth_modals', ['next' => $next]) ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(asset('js/core.js')) ?>"></script>
<?php foreach ($scripts as $script) : ?>
  <script src="<?= e(asset('js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
