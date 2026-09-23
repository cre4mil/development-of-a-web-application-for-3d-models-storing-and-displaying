<?php
require_once 'connect.php';
$uid = (int)($_SESSION['uid'] ?? 0);
$mid = (int)($_GET['id'] ?? 0);
if ($mid <= 0) { header('Location: index.php'); exit; }

$licenses = [
  'CC BY'=>'Creative Commons Attribution 4.0',
  'CC BY-SA'=>'CC Attribution-ShareAlike 4.0',
  'CC BY-ND'=>'CC Attribution-NoDerivs 4.0',
  'CC BY-NC'=>'CC Attribution-NonCommercial 4.0',
  'CC BY-NC-SA'=>'CC Attribution-NonCommercial-ShareAlike 4.0',
  'CC BY-NC-ND'=>'CC Attribution-NonCommercial-NoDerivs 4.0',
  'CC0'=>'Public Domain (CC0)',
  'All Rights Reserved'=>'All Rights Reserved',
];

$st = $pdo->prepare("
  SELECT m.*, u.username AS uploader,
    (SELECT COUNT(*) FROM likes    WHERE model_id=m.id) AS like_count,
    (SELECT COUNT(*) FROM comments WHERE model_id=m.id) AS comment_count
  FROM models m JOIN users u ON u.id=m.user_id
  WHERE m.id=? AND (m.is_public=1 OR m.user_id=?)
");
$st->execute([$mid, $uid]);
$m = $st->fetch();
if (!$m) { header('Location: index.php'); exit; }

if ($uid !== (int)$m['user_id']) {
  $pdo->prepare("UPDATE models SET view_count=view_count+1 WHERE id=?")->execute([$mid]);
  $m['view_count']++;
}

$tags = $pdo->prepare("SELECT t.name FROM tags t JOIN model_tags mt ON mt.tag_id=t.id WHERE mt.model_id=?");
$tags->execute([$mid]);
$tagList = $tags->fetchAll(PDO::FETCH_COLUMN);

$isLiked = false; $isSaved = false; $isPurchased = false; $isPending = false;
if ($uid) {
  $l=$pdo->prepare("SELECT 1 FROM likes WHERE user_id=? AND model_id=?"); $l->execute([$uid,$mid]); $isLiked=(bool)$l->fetch();
  $s=$pdo->prepare("SELECT 1 FROM collections WHERE user_id=? AND model_id=?"); $s->execute([$uid,$mid]); $isSaved=(bool)$s->fetch();
  $p=$pdo->prepare("SELECT status FROM orders WHERE buyer_id=? AND model_id=? ORDER BY created_at DESC LIMIT 1");
  $p->execute([$uid,$mid]);
  $orderStatus = $p->fetchColumn();
  $isPurchased = ($orderStatus === 'approved');
  $isPending = ($orderStatus === 'pending');
}

$sug = $pdo->prepare("SELECT m.id, m.title, m.filename, m.thumb, m.view_count, m.price, u.username as uploader,
    (SELECT COUNT(*) FROM likes WHERE model_id=m.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE model_id=m.id) as comment_count
    FROM models m JOIN users u ON u.id=m.user_id
    WHERE m.is_public=1 AND m.id != ?
    ORDER BY RAND() LIMIT 8");
$sug->execute([$mid]);
$suggested = $sug->fetchAll();

function hf($b){
  if ($b <= 0) {
    return '0 B';
  }
  $u = ['B','KB','MB','GB'];
  $p = floor(log($b, 1024));
  return round($b / pow(1024, $p), 2) . ' ' . $u[$p];
}
$ext     = strtolower(pathinfo($m['filename'],PATHINFO_EXTENSION));

const UPLOADS_DIRECTORY = __DIR__ . '/uploads/';
const CONVERTED_FORMAT_LABEL = 'Converted format';
$dlFiles = [];
if (!empty($m['filename'])) {
  $abs = UPLOADS_DIRECTORY . $m['filename'];
  if (is_file($abs)) {
    $dlFiles[] = ['format'=>'original', 'ext'=>strtoupper(pathinfo($m['filename'], PATHINFO_EXTENSION)), 'size'=>hf(filesize($abs)), 'label'=>'Original format'];
  }
}
if (!empty($m['file_gltf'])) {
  $abs = UPLOADS_DIRECTORY . $m['file_gltf'];
  if (is_file($abs)) {
    $dlFiles[] = ['format'=>'gltf', 'ext'=>'GLTF', 'size'=>hf(filesize($abs)), 'label'=>CONVERTED_FORMAT_LABEL];
  }
}
if (!empty($m['file_glb'])) {
  $abs = UPLOADS_DIRECTORY . $m['file_glb'];
  if (is_file($abs)) {
    $dlFiles[] = ['format'=>'glb', 'ext'=>'GLB', 'size'=>hf(filesize($abs)), 'label'=>CONVERTED_FORMAT_LABEL];
  }
}
if (!empty($m['file_usdz'])) {
  $abs = UPLOADS_DIRECTORY . $m['file_usdz'];
  if (is_file($abs)) {
    $dlFiles[] = ['format'=>'usdz', 'ext'=>'USDZ', 'size'=>hf(filesize($abs)), 'label'=>CONVERTED_FORMAT_LABEL];
  }
}
if (!empty($m['file_obj'])) {
  $abs = UPLOADS_DIRECTORY . $m['file_obj'];
  if (is_file($abs)) {
    $dlFiles[] = ['format'=>'obj', 'ext'=>'OBJ', 'size'=>hf(filesize($abs)), 'label'=>CONVERTED_FORMAT_LABEL];
  }
}

$file    = 'uploads/'.$m['filename'];
$absPath = UPLOADS_DIRECTORY . $m['filename'];
$size    = is_file($absPath)?hf(@filesize($absPath)):'-';
$date    = $m['created_at']?date('d/m/Y H:i',strtotime($m['created_at'])):'-';
$csrf    = $_SESSION['csrf'] ?? '';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($m['title']) ?> – 3D Gallery</title>
  <link rel="stylesheet" href="style.css?v=<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time() ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
  <style>
    .model-page-viewer{background:#222;border-radius:12px;overflow:hidden;height:60vh;min-height:380px;position:relative;}
    .model-page-viewer canvas{width:100%!important;height:100%!important;display:block;}
    .info-card{background:var(--card-bg,#1e1e2e);border-radius:12px;padding:1.4rem;color:#e0e0e0;}
    .license-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;padding:.3rem .7rem;border-radius:20px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);}
    .tag-chip{display:inline-block;padding:.2rem .7rem;border-radius:20px;background:rgba(255,191,0,.15);color:#fbbf24;font-size:.8rem;text-decoration:none;margin:.15rem;}
    .tag-chip:hover{background:rgba(255,191,0,.3);color:#fbbf24;}
    .stat-box{text-align:center;padding:.6rem;background:rgba(255,255,255,.05);border-radius:8px;}
    .stat-box .num{font-size:1.4rem;font-weight:700;color:#fff;}
    .stat-box .lbl{font-size:.72rem;color:#888;}
    .comment-item{padding:1rem;border-radius:8px;background:rgba(255,255,255,.04);margin-bottom:.6rem;}
    .comment-item .comment-meta{font-size:.78rem;color:#888;}
    .comment-body{color:#d0d0d0;white-space:pre-wrap;word-break:break-word;}
    .viewer-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:.9rem;z-index:2;background:#222;}
    /* Model Inspector UI */
    .model-inspector { position: absolute; top: 0; left: 0; width: 260px; height: 100%; background: rgba(20,20,20,0.95); backdrop-filter: blur(10px); border-right: 1px solid rgba(255,255,255,0.1); z-index: 10; color: #ddd; display: flex; flex-direction: column; overflow-y: auto; font-size: 0.85rem; transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
    .model-inspector.active { transform: translateX(0); }
    .model-inspector::-webkit-scrollbar { width: 6px; }
    .model-inspector::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
    .model-inspector-toggle { position: absolute; top: 15px; left: 15px; z-index: 9; background: rgba(20,20,20,0.8); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 12px; cursor: pointer; backdrop-filter: blur(4px); transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
    .model-inspector-toggle:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
    .inspector-header { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); font-weight: 600; font-size: 1.05rem; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.4); color: #fff; }
    .inspector-section { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .inspector-section-title { font-size: 0.72rem; color: #999; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
    .inspector-btn { display: flex; align-items: center; gap: 12px; padding: 8px 12px; width: 100%; background: transparent; border: none; color: #aaa; text-align: left; border-radius: 8px; cursor: pointer; margin-bottom: 4px; transition: all 0.2s ease; }
    .inspector-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
    .inspector-btn.active { background: #1caad9; color: #fff; font-weight: 600; padding-left: 12px; border-radius: 4px; }
    .inspector-btn i { font-size: 1.15rem; width: 20px; text-align: center; }
  </style>
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="index.php" class="logo">
      <span class="logo-icon"><i class="bi bi-box"></i></span>
      3D Gallery
    </a>
    <nav aria-label="เมนูหลัก">
      <?php if ($uid): ?>
        <?php if (!empty($_SESSION['is_admin'])): ?>
        <a class="nav-link-c text-info" href="admin.php"><i class="bi bi-shield-lock-fill"></i> Dashboard</a>
        <?php endif; ?>
        <a class="nav-link-c text-warning" href="#" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-cloud-upload"></i> Upload</a>

        <div class="nav-item dropdown d-inline-block">
          <a href="#" class="user-toggle" data-bs-toggle="dropdown">
            <span class="avatar-circle"><?= htmlspecialchars(strtoupper(substr($_SESSION['uname'],0,1))) ?></span>
            <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['uname']) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li class="dropdown-header">
              <div class="name fw-bold text-dark"><?= htmlspecialchars($_SESSION['uname']) ?></div>
              <div class="meta"><?= !empty($_SESSION['is_admin']) ? '<span class="badge bg-danger mt-1">ผู้ดูแลระบบ</span>' : 'เข้าสู่ระบบแล้ว' ?></div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php if (!empty($_SESSION['is_admin'])): ?>
              <li><a class="dropdown-item fw-bold text-primary" href="admin.php"><i class="bi bi-shield-lock-fill me-1"></i> แผงควบคุม (Admin)</a></li>
              <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> โปรไฟล์</a></li>
            <?php if (empty($_SESSION['is_admin'])): ?>
            <li><a class="dropdown-item" href="like.php"><i class="bi bi-heart me-1"></i> โมเดลที่ชอบ</a></li>
            <li><a class="dropdown-item" href="payment.php"><i class="bi bi-credit-card me-1"></i> ชำระเงิน / การสั่งซื้อ</a></li>
            <li><a class="dropdown-item" href="creator_earnings.php"><i class="bi bi-cash-stack me-1"></i> รายได้</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="#" class="nav-link-c" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
        <a href="#" class="nav-link-c" style="color: #60a5fa;" data-bs-toggle="modal" data-bs-target="#registerModal">Sign Up</a>
      <?php endif ?>
    </nav>
  </div>
</header>

<div class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
      <li class="breadcrumb-item active"><?= htmlspecialchars($m['title']) ?></li>
    </ol>
  </nav>

  <div class="row g-4">
    <!-- Left Column: Viewer + Details -->
    <div class="col-lg-8">
      <!-- Viewer -->
      <div class="model-page-viewer position-relative mb-4" id="viewerWrap" style="border-radius:12px;overflow:hidden;background-color:#111;height:65vh;min-height:400px;border:1px solid #ddd;">
        <div class="viewer-toolbar">
          <button class="viewer-toolbar-btn" title="Help"><i class="bi bi-question-lg"></i></button>
          <button class="viewer-toolbar-btn position-relative" title="Settings">
            <i class="bi bi-gear"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info" style="font-size: 0.5rem;">HD</span>
          </button>
          <button class="viewer-toolbar-btn" id="btnToggleInspector" title="Model Inspector"><i class="bi bi-layers"></i></button>
          <button class="viewer-toolbar-btn" title="VR"><i class="bi bi-badge-vr"></i></button>
          <button class="viewer-toolbar-btn" id="btnFullscreen" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        </div>
        <div class="model-inspector" id="modelInspector">
          <div class="inspector-header">
            <div><i class="bi bi-box"></i> Model Inspector</div>
            <button class="btn btn-sm text-white p-0" id="btnCloseInspector"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">Wireframe Color</div>
            <div class="d-flex gap-2">
              <button class="color-swatch bg-white active page-swatch d-flex align-items-center justify-content-center" data-color="default"><i class="bi bi-x-lg text-dark" style="font-size: 0.8rem;"></i></button>
              <button class="color-swatch page-swatch" data-color="#000000" style="background-color: #000000; border-color: rgba(255,255,255,0.2)"></button>
              <button class="color-swatch page-swatch" data-color="#cccccc" style="background-color: #cccccc;"></button>
              <button class="color-swatch page-swatch" data-color="#ff0000" style="background-color: #ff0000;"></button>
              <button class="color-swatch page-swatch" data-color="#0000ff" style="background-color: #0000ff;"></button>
              <button class="color-swatch page-swatch" data-color="#00ff00" style="background-color: #00ff00;"></button>
              <button class="color-swatch page-swatch" data-color="#ffff00" style="background-color: #ffff00;"></button>
            </div>
          </div>
          <div class="inspector-section">
            <label class="form-check-label d-flex align-items-center gap-2" style="cursor:pointer;" for="singleSidedToggle">
              <div class="form-check form-switch m-0 p-0 fs-5 d-flex align-items-center">
                <input class="form-check-input m-0" type="checkbox" id="singleSidedToggle">
              </div>
              <span class="fs-6 fw-bold">Single Sided</span>
            </label>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">Render (2)</div>
            <button class="inspector-btn active" data-mode="final"><i class="bi bi-palette"></i> Final Render</button>
            <button class="inspector-btn" data-mode="noPost"><i class="bi bi-palette"></i> No Post-Processing</button>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">Material Channels (7)</div>
            <button class="inspector-btn" data-mode="baseColor"><i class="bi bi-droplet"></i> Base Color</button>
            <button class="inspector-btn" data-mode="metalness"><i class="bi bi-lightning"></i> Metalness</button>
            <button class="inspector-btn" data-mode="roughness"><i class="bi bi-vinyl"></i> Roughness</button>
            <button class="inspector-btn" data-mode="normalMap"><i class="bi bi-border-outer"></i> Normal Map</button>
            <button class="inspector-btn" data-mode="opacity"><i class="bi bi-grid-3x3-gap"></i> Opacity</button>
            <button class="inspector-btn" data-mode="specular"><i class="bi bi-circle-half"></i> Specular F0</button>
            <button class="inspector-btn" data-mode="vertexColor"><i class="bi bi-palette2"></i> Vertex Color</button>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">Geometry (4)</div>
            <button class="inspector-btn" data-mode="matcap"><i class="bi bi-record-circle"></i> Matcap</button>
            <button class="inspector-btn" data-mode="matcapSurface"><i class="bi bi-record-circle-fill"></i> Matcap+Surface</button>
            <button class="inspector-btn" data-overlay="wireframe"><i class="bi bi-grid-3x3"></i> Wireframe</button>
            <button class="inspector-btn" data-mode="normalRender"><i class="bi bi-arrows-move"></i> Vertex Normals</button>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">UV (1)</div>
            <button class="inspector-btn" data-mode="uv"><i class="bi bi-grid-1x2"></i> UV Checker</button>
          </div>
        </div>

        <div class="viewer-loading" id="loadingMsg"><i class="bi bi-hourglass-split me-2"></i>กำลังโหลดโมเดล...</div>
        <div id="viewer3d" style="width:100%;height:100%;"></div>
      </div>

      <!-- Details -->
      <h2 class="fw-bold mb-1 text-dark mt-2" style="font-size: 1.6rem;"><?= htmlspecialchars($m['title']) ?></h2>
      <div class="text-muted small mb-4">3D Model</div>

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-secondary-subtle">
        <div class="d-flex align-items-center mb-3 mb-md-0">
          <div class="me-3" style="width: 48px; height: 48px; background: #eee; color: #333; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.2rem; font-weight: bold; border: 1px solid #ccc;">
            <?= mb_strtoupper(mb_substr($m['uploader'],0,1,'UTF-8')) ?>
          </div>
          <div>
            <div class="fw-bold text-dark" style="font-size: 1.05rem;"><?= htmlspecialchars($m['uploader']) ?></div>
            <button id="btnFollow" class="btn btn-sm text-white mt-1 fw-bold" style="border-radius: 4px; font-size: 0.65rem; padding: 0.2rem 0.8rem; background-color: #00aced; border: none; letter-spacing: 0.5px;">FOLLOW</button>
          </div>
        </div>

        <div class="d-flex gap-3 align-items-center text-muted small">
          <div><i class="bi bi-eye"></i> <?= number_format($m['view_count']) ?>k</div>
          <div><i class="bi bi-chat-square-text"></i> <?= (int)$m['comment_count'] ?></div>
          <div class="text-dark fw-bold border rounded px-2 py-1" style="background:#f8f9fa; border-color:#ddd;"><i class="bi bi-star-fill text-warning" style="font-size:0.8rem;"></i> <span id="likeCountTop"><?= (int)$m['like_count'] ?></span></div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-3 mb-4 pb-4 border-bottom border-secondary-subtle align-items-center">
        <?php if($m['price'] > 0 && $uid !== (int)$m['user_id']): ?>
          <?php if($isPurchased): ?>
            <a href="#" class="text-info fw-bold text-decoration-none d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#downloadModal" style="font-size: 0.95rem;">
              <i class="bi bi-download me-1 fs-5"></i> Download 3D Model
            </a>
          <?php elseif($isPending): ?>
            <span class="text-secondary fw-bold d-flex align-items-center" style="font-size: 0.95rem;" title="รอการตรวจสอบการชำระเงิน">
              <i class="bi bi-clock-history me-1 fs-5"></i> รอตรวจสอบ...
            </span>
          <?php else: ?>
            <?php if($uid): ?>
              <a href="#" class="text-info fw-bold text-decoration-none d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#paymentModal" style="font-size: 0.95rem;">
                <i class="bi bi-cart2 me-1 fs-5"></i> Buy Model (฿<?= number_format($m['price'], 2) ?>)
              </a>
            <?php else: ?>
              <a href="#" class="text-info fw-bold text-decoration-none d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#loginModal" style="font-size: 0.95rem;">
                <i class="bi bi-cart2 me-1 fs-5"></i> Buy Model (฿<?= number_format($m['price'], 2) ?>)
              </a>
            <?php endif; ?>
          <?php endif; ?>
        <?php else: ?>
          <a href="#" class="text-info fw-bold text-decoration-none d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#downloadModal" style="font-size: 0.95rem;">
            <i class="bi bi-download me-1 fs-5"></i> Download 3D Model
          </a>
        <?php endif; ?>

        <?php if($uid): ?>
        <button id="btnSave" class="btn btn-link text-muted text-decoration-none d-flex align-items-center p-0" style="font-size: 0.95rem;" data-id="<?= $mid ?>" data-saved="<?= $isSaved?'1':'0' ?>">
          <i class="bi <?= $isSaved?'bi-collection-fill':'bi-plus-lg' ?> me-1 fs-5"></i> <span id="saveText"><?= $isSaved?'In collections':'Add To' ?></span>
        </button>
        <?php endif ?>

        <button class="btn btn-link text-muted text-decoration-none d-flex align-items-center p-0" style="font-size: 0.95rem;">
          <i class="bi bi-code-slash me-1 fs-5"></i> Embed
        </button>
        <button class="btn btn-link text-muted text-decoration-none d-flex align-items-center p-0" style="font-size: 0.95rem;">
          <i class="bi bi-share me-1 fs-5"></i> Share
        </button>

        <div class="ms-auto">
          <button id="btnLike" class="btn btn-link <?= $isLiked?'text-danger':'text-muted' ?> text-decoration-none d-flex align-items-center p-0" style="font-size: 0.95rem;" data-id="<?= $mid ?>" data-liked="<?= $isLiked?'1':'0' ?>">
             <i class="bi <?= $isLiked?'bi-heart-fill':'bi-heart' ?> me-1 fs-5"></i> <span id="likeCount"><?= $isLiked?'Liked':'Like' ?></span>
          </button>
        </div>
      </div>

      <div class="text-muted small mb-4 d-flex gap-3 border-bottom pb-3">
        <span><i class="bi bi-triangle"></i> Triangles: <strong>14.2k</strong></span>
        <span><i class="bi bi-diagram-3"></i> Vertices: <strong>8.5k</strong></span>
        <a href="#" class="text-info text-decoration-none">More model information</a>
      </div>

      <?php if($m['description']): ?>
      <p class="text-dark mb-4" style="white-space:pre-wrap; line-height: 1.6; font-size: 0.95rem;"><?= htmlspecialchars($m['description']) ?></p>
      <?php endif ?>

      <div class="text-muted small mb-4">
        <div class="mb-2">License: <strong class="text-dark"><?= htmlspecialchars($m['license']) ?></strong> <a href="#" class="text-info text-decoration-none">Learn more</a></div>
        <div class="mb-2"><i class="bi bi-clock"></i> Published <?= $date ?></div>
      </div>

      <?php if($tagList): ?>
      <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach($tagList as $t): ?>
          <a href="index.php?tag=<?= urlencode($t) ?>" class="badge text-decoration-none fw-normal px-3 py-2" style="background-color: #eee; color: #666; border-radius: 4px; border: 1px solid #ddd;">#<?= htmlspecialchars($t) ?></a>
        <?php endforeach ?>
      </div>
      <?php endif ?>
      <!-- Comments -->
      <div class="mt-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2"></i>ความคิดเห็น <span id="cCount" class="text-muted fs-6">(<?= (int)$m['comment_count'] ?>)</span></h5>

        <?php if($uid): ?>
        <form id="commentForm" class="mb-4">
          <div class="d-flex gap-2">
            <div class="avatar-circle flex-shrink-0"><?= htmlspecialchars(strtoupper(substr($_SESSION['uname'],0,1))) ?></div>
            <div class="flex-grow-1">
              <label class="visually-hidden" for="commentBody">ความคิดเห็น</label>
              <textarea id="commentBody" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="เขียนความคิดเห็น..." maxlength="1000"></textarea>
              <button type="submit" class="btn btn-primary btn-sm mt-2">โพสต์</button>
            </div>
          </div>
        </form>
        <?php else: ?>
        <div class="alert alert-dark mb-3"><a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">เข้าสู่ระบบ</a> เพื่อแสดงความคิดเห็น</div>
        <?php endif ?>

        <div id="commentList"></div>
      </div>
    </div>

    <!-- Right Column: Suggested Models -->
    <div class="col-lg-4">
      <h6 class="text-muted fw-bold mb-3" style="letter-spacing: 1px; font-size: 0.8rem;">SUGGESTED 3D MODELS</h6>
      <div class="d-flex flex-column gap-3">
        <?php foreach($suggested as $s): ?>
          <a href="model.php?id=<?= $s['id'] ?>" class="text-decoration-none">
            <div class="card bg-white shadow-sm border-0 d-flex flex-row gap-3 align-items-center p-2" style="border-radius: 4px; border: 1px solid #eee !important;">
              <div style="width: 100px; height: 70px; background-color: #eee; border-radius: 4px; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;">
                <img src="uploads/<?= htmlspecialchars($s['thumb'] ?? '') ?>" alt="thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                <?php if($s['price'] > 0): ?>
                  <span class="position-absolute bottom-0 end-0 bg-dark text-white p-1" style="font-size: 0.6rem; border-top-left-radius: 4px;">฿<?= number_format($s['price']) ?></span>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1">
                <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem; max-width: 150px;"><?= htmlspecialchars($s['title']) ?></div>
                <div class="text-muted small mb-1"><?= htmlspecialchars($s['uploader']) ?></div>
                <div class="text-muted" style="font-size: 0.75rem;">
                  <i class="bi bi-eye"></i> <?= number_format($s['view_count']) ?>k &nbsp;
                  <i class="bi bi-chat-square-text"></i> <?= (int)$s['comment_count'] ?> &nbsp;
                  <i class="bi bi-star"></i> <?= (int)$s['like_count'] ?>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if(empty($suggested)): ?>
          <div class="text-muted small">No models found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include_once 'view.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/build/three.min.js" integrity="sha384-XIeZcIwWx2i8CVKHEeXtUv7cYAaKNZEqfaxJBdCjo0PcBsE/VWGKsa2SFDKvtW5S" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/GLTFLoader.js" integrity="sha384-+bpKS48ZxfAa8n4kv4SZhJNbgTIxZ0zQ1Y/dqH4hrrHViarGZaihLypNJGSGa/p6" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/OBJLoader.js" integrity="sha384-UWFC8mrevmKCZhKbJ/8/dqLrRAvHArRwJCKjwruJuXyhsebGMFsIK5zrn+R9r+fT" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/controls/OrbitControls.js" integrity="sha384-fcwmxprR7ntks0MLHmwtgWca24P1SKyvyrYWvHvD6ciUjdZ1iqG0YVYpI0KfpwB1" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/helpers/VertexNormalsHelper.js" integrity="sha384-KS9U3o57P0V9tnNlY5pl7bKySkhd5iHM2jFF4H3tUwJhMGozcnS1WtEOFkCZxCKt" crossorigin="anonymous"></script>
<script>
const MODEL_ID = <?= $mid ?>;
const CUR_UID  = <?= $uid ?>;
const MODEL_FILE = '<?= addslashes($file) ?>';
const MODEL_EXT  = '<?= $ext ?>';

// --- Three.js Viewer ---
const wrap   = document.getElementById('viewerWrap');
const dom    = document.getElementById('viewer3d');
const scene  = new THREE.Scene(); scene.background = new THREE.Color(0xcccccc);
const camera = new THREE.PerspectiveCamera(40,1,0.01,10000);
const renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});
renderer.setPixelRatio(Math.min(devicePixelRatio,2));
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.0;
dom.appendChild(renderer.domElement);
scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dLight = new THREE.DirectionalLight(0xffffff,1.3); dLight.position.set(5,6,7); scene.add(dLight);
const controls = new THREE.OrbitControls(camera,renderer.domElement); controls.enableDamping=true;

// --- Inspector Logic ---
let originalMaterials = new Map();
let currentMode = 'final';
let loadedModelObj = null;
let wireframeColor = 'default';
let isWireframeActive = false;

const textureLoader = new THREE.TextureLoader();
const uvTexture = textureLoader.load('https://raw.githubusercontent.com/mrdoob/three.js/master/examples/textures/uv_grid_opengl.jpg');
uvTexture.wrapS = THREE.RepeatWrapping; uvTexture.wrapT = THREE.RepeatWrapping; uvTexture.repeat.set(2, 2);

function createMatcap() {
  const canvas = document.createElement('canvas'); canvas.width = 256; canvas.height = 256;
  const ctx = canvas.getContext('2d');
  const cx = 128, cy = 128, r = 126;
  const gradient = ctx.createRadialGradient(cx-30, cy-30, 10, cx, cy, r);
  gradient.addColorStop(0, '#ffffff'); gradient.addColorStop(0.5, '#aaaaaa'); gradient.addColorStop(1, '#222222');
  ctx.fillStyle = gradient; ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
  return new THREE.CanvasTexture(canvas);
}
const matcapTexture = createMatcap();

function storeOriginalMaterials(obj) {
  originalMaterials.clear();
  obj.traverse(child => {
    if (child.isMesh && child.material) {
      originalMaterials.set(child, Array.isArray(child.material) ? child.material.map(m => m.clone()) : child.material.clone());
    }
  });
}



function setInspectorMode(mode) {
  if (!loadedModelObj) return;
  currentMode = mode;

  if (mode === 'noPost') {
    renderer.toneMapping = THREE.NoToneMapping;
  } else {
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
  }

  loadedModelObj.traverse(child => {
    if (child.isMesh) {
      const origMat = originalMaterials.get(child);
      if (!origMat) return;

      const processMaterial = (mat) => {
        const isSingleSided = document.getElementById('singleSidedToggle')?.checked;
        const currentSide = isSingleSided ? THREE.FrontSide : (mat.side || THREE.FrontSide);

        let newMat;
        if (mode === 'final' || mode === 'noPost') {
          newMat = mat.clone();
        } else if (mode === 'baseColor') {
          newMat = new THREE.MeshBasicMaterial({ map: mat.map, color: mat.map ? 0xffffff : (mat.color || 0xcccccc) });
        } else if (mode === 'metalness') {
          newMat = new THREE.MeshBasicMaterial({ map: mat.metalnessMap, color: mat.metalnessMap ? 0xffffff : new THREE.Color(mat.metalness||0, mat.metalness||0, mat.metalness||0) });
        } else if (mode === 'roughness') {
          newMat = new THREE.MeshBasicMaterial({ map: mat.roughnessMap, color: mat.roughnessMap ? 0xffffff : new THREE.Color(mat.roughness||1, mat.roughness||1, mat.roughness||1) });
        } else if (mode === 'normalMap') {
          newMat = new THREE.MeshBasicMaterial({ map: mat.normalMap, color: mat.normalMap ? 0xffffff : 0x8080ff });
        } else if (mode === 'opacity') {
          newMat = new THREE.MeshBasicMaterial({ map: mat.alphaMap, color: mat.alphaMap ? 0xffffff : new THREE.Color(mat.opacity!==undefined?mat.opacity:1, mat.opacity!==undefined?mat.opacity:1, mat.opacity!==undefined?mat.opacity:1) });
        } else if (mode === 'specular') {
          newMat = new THREE.MeshBasicMaterial({ color: mat.specular || new THREE.Color(0x333333) });
        } else if (mode === 'vertexColor') {
          newMat = new THREE.MeshBasicMaterial({ vertexColors: true });
        } else if (mode === 'matcap') {
          newMat = new THREE.MeshMatcapMaterial({ matcap: matcapTexture });
        } else if (mode === 'matcapSurface') {
          newMat = new THREE.MeshMatcapMaterial({ matcap: matcapTexture, normalMap: mat.normalMap, normalScale: mat.normalScale });
        } else if (mode === 'uv') {
          newMat = new THREE.MeshBasicMaterial({ map: uvTexture, color: 0xffffff });
        } else if (mode === 'normalRender') {
          newMat = new THREE.MeshNormalMaterial();
        } else {
          newMat = new THREE.MeshBasicMaterial({ color: 0xcccccc });
        }

        newMat.side = currentSide;
        if (mat.transparent) {
            newMat.transparent = mat.transparent;
            newMat.opacity = mat.opacity;
            newMat.alphaTest = mat.alphaTest;
        }

        // Push base mesh back slightly to prevent z-fighting with wireframe overlay
        newMat.polygonOffset = true;
        newMat.polygonOffsetFactor = 1;
        newMat.polygonOffsetUnits = 1;

        return newMat;
      };

      if (Array.isArray(origMat)) {
        child.material = origMat.map(processMaterial);
      } else {
        child.material = processMaterial(origMat);
      }
      child.material.needsUpdate = true;
    }
  });
  updateWireframeOverlay();
}

function getFastWireframeGeometry(geometry) {
    if (geometry.userData.fastWireframeGeo) return geometry.userData.fastWireframeGeo;
    const geom = new THREE.BufferGeometry();
    geom.setAttribute('position', geometry.getAttribute('position'));
    let indices;
    if (geometry.index) {
        const arr = geometry.index.array;
        indices = new (geometry.getAttribute('position').count > 65535 ? Uint32Array : Uint16Array)(arr.length * 2);
        let j = 0;
        for (let i = 0, l = arr.length; i < l; i += 3) {
            const a = arr[i], b = arr[i+1], c = arr[i+2];
            indices[j++] = a; indices[j++] = b;
            indices[j++] = b; indices[j++] = c;
            indices[j++] = c; indices[j++] = a;
        }
    } else {
        const count = geometry.getAttribute('position').count;
        indices = new (count > 65535 ? Uint32Array : Uint16Array)(count * 2);
        let j = 0;
        for (let i = 0; i < count; i += 3) {
            indices[j++] = i;   indices[j++] = i+1;
            indices[j++] = i+1; indices[j++] = i+2;
            indices[j++] = i+2; indices[j++] = i;
        }
    }
    geom.setIndex(new THREE.BufferAttribute(indices, 1));
    geometry.userData.fastWireframeGeo = geom;
    return geom;
}

function updateWireframeOverlay() {
  if (!loadedModelObj) return;
  loadedModelObj.traverse(child => {
     if (child.isMesh) {
       if (child.userData.wireframeHelper) {
          child.remove(child.userData.wireframeHelper);
          child.userData.wireframeHelper.material.dispose();
          delete child.userData.wireframeHelper;
       }
       if (isWireframeActive) {
          const c = wireframeColor === 'default' ? '#00ff00' : wireframeColor;
          const wireMat = new THREE.LineBasicMaterial({ color: new THREE.Color(c), depthTest: true, transparent: true, opacity: 0.8 });
          const fastWireGeo = getFastWireframeGeometry(child.geometry);
          const wireMesh = new THREE.LineSegments(fastWireGeo, wireMat);
          child.add(wireMesh);
          child.userData.wireframeHelper = wireMesh;
       }
     }
  });
}

// --- Inspector UI Binding ---
document.getElementById('singleSidedToggle')?.addEventListener('change', (e) => {
  if (!loadedModelObj) return;
  const activeBtn = document.querySelector('.inspector-btn[data-mode].active');
  setInspectorMode(activeBtn ? activeBtn.dataset.mode : 'final');
});
document.querySelectorAll('.page-swatch').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.page-swatch').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    wireframeColor = this.dataset.color;

    // Automatically enable wireframe if a specific color is chosen
    if (wireframeColor !== 'default' && !isWireframeActive) {
       isWireframeActive = true;
       document.querySelector('.inspector-btn[data-overlay="wireframe"]')?.classList.add('active');
    } else if (wireframeColor === 'default' && isWireframeActive) {
       isWireframeActive = false;
       document.querySelector('.inspector-btn[data-overlay="wireframe"]')?.classList.remove('active');
    }
    updateWireframeOverlay();
  });
});
  document.getElementById('btnToggleInspector').addEventListener('click', () => {
    document.getElementById('modelInspector').classList.toggle('active');
  });
  document.getElementById('btnCloseInspector').addEventListener('click', () => {
    document.getElementById('modelInspector').classList.remove('active');
  });
  document.getElementById('btnFullscreen')?.addEventListener('click', () => {
    const wrap = document.getElementById('viewerWrap');
    if (!document.fullscreenElement) wrap.requestFullscreen().catch(()=>{});
    else document.exitFullscreen();
  });
  document.querySelectorAll('.inspector-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    if (this.dataset.overlay === 'wireframe') {
       isWireframeActive = !isWireframeActive;
       if (isWireframeActive) {
          this.classList.add('active');
          if (wireframeColor === 'default') {
             wireframeColor = '#cccccc';
             document.querySelectorAll('.page-swatch').forEach(b => b.classList.remove('active'));
             document.querySelector('.page-swatch[data-color="#cccccc"]')?.classList.add('active');
          }
       } else {
          this.classList.remove('active');
       }
       updateWireframeOverlay();
       return;
    }

    if (this.dataset.mode) {
       document.querySelectorAll('.inspector-btn[data-mode]').forEach(b => b.classList.remove('active'));
       this.classList.add('active');
       setInspectorMode(this.dataset.mode);
    }
  });
});

// --- Toolbar Logic ---
document.querySelector('#viewerWrap .viewer-toolbar-btn[title="Settings"]')?.addEventListener('click', function() {
  const isHD = this.classList.contains('active');
  this.classList.toggle('active');
  renderer.setPixelRatio(isHD ? 1 : window.devicePixelRatio);
  const badge = this.querySelector('.badge');
  if(badge) badge.textContent = isHD ? 'SD' : 'HD';
});

if (navigator.xr) renderer.xr.enabled = true;
document.querySelector('#viewerWrap .viewer-toolbar-btn[title="VR"]')?.addEventListener('click', () => {
  if(navigator.xr) {
    navigator.xr.requestSession('immersive-vr').then(session => renderer.xr.setSession(session)).catch(e => alert('VR Not Supported: ' + e.message));
  } else {
    alert('WebXR not supported in this browser.');
  }
});

function resize(){
  const w=wrap.clientWidth, h=wrap.clientHeight||480;
  renderer.setSize(w,h,false); camera.aspect=w/h||1; camera.updateProjectionMatrix();
}
new ResizeObserver(resize).observe(wrap);

function fitCamera(obj){
  const box=new THREE.Box3().setFromObject(obj);
  const sz=box.getSize(new THREE.Vector3()), c=box.getCenter(new THREE.Vector3());
  const ms=Math.max(sz.x,sz.y,sz.z)||1, fov=THREE.MathUtils.degToRad(camera.fov);
  const d=Math.max(ms/(2*Math.tan(fov/2)), ms/(2*Math.tan(fov/2)/camera.aspect))*1.35;
  camera.near=d/100; camera.far=d*100; camera.updateProjectionMatrix();
  camera.position.copy(c.clone().add(new THREE.Vector3(d,d,d)));
  controls.target.copy(c); controls.update();
}

function loadModel(){
  const ext = MODEL_EXT;
  if(ext==='glb'||ext==='gltf'){
    const L=window.GLTFLoader?new GLTFLoader():new THREE.GLTFLoader();
    L.load(MODEL_FILE,g=>{
      const obj=g.scene||g.scenes?.[0]||g;
      loadedModelObj = obj;
      scene.add(obj); fitCamera(obj);
      storeOriginalMaterials(obj);
      document.getElementById('loadingMsg').style.display='none';
    },undefined,()=>{ document.getElementById('loadingMsg').textContent='โหลดโมเดลไม่สำเร็จ'; });
  } else if(ext==='obj'){
    const L=window.OBJLoader?new OBJLoader():new THREE.OBJLoader();
    L.load(MODEL_FILE,obj=>{
      loadedModelObj = obj;
      scene.add(obj); fitCamera(obj);
      storeOriginalMaterials(obj);
      document.getElementById('loadingMsg').style.display='none';
    },undefined,()=>{ document.getElementById('loadingMsg').textContent='โหลดโมเดลไม่สำเร็จ'; });
  }
}
resize();
loadModel();
(function animate(){ requestAnimationFrame(animate); controls.update(); renderer.render(scene,camera); })();

// --- Like ---
document.getElementById('btnLike')?.addEventListener('click', async function(){
  <?php if(!$uid): ?>
  new bootstrap.Modal(document.getElementById('loginModal')).show(); return;
  <?php endif ?>

  const liked=this.dataset.liked==='1';
  this.dataset.liked=liked?'0':'1';
  this.disabled=true;

  fetch('like_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+this.dataset.id})
    .catch(()=>{});

  let cnt = document.getElementById('likeCountTop');
  let txt = document.getElementById('likeCount');
  if(!liked) {
      this.classList.remove('text-muted');
      this.classList.add('text-danger');
      this.querySelector('i').className='bi bi-heart-fill me-1 fs-5';
      if(cnt) cnt.textContent=parseInt(cnt.textContent)+1;
      if(txt) txt.textContent='Liked';
  }
  else {
      this.classList.remove('text-danger');
      this.classList.add('text-muted');
      this.querySelector('i').className='bi bi-heart me-1 fs-5';
      if(cnt) cnt.textContent=Math.max(0,parseInt(cnt.textContent)-1);
      if(txt) txt.textContent='Like';
  }
  this.disabled=false;
});

// --- Follow ---
document.getElementById('btnFollow')?.addEventListener('click', function() {
  <?php if(!$uid): ?>
  new bootstrap.Modal(document.getElementById('loginModal')).show(); return;
  <?php endif ?>

  if(this.textContent === 'FOLLOWING') {
      this.textContent = 'FOLLOW';
      this.style.backgroundColor = '#00aced';
      this.style.color = '#fff';
      this.style.border = 'none';
  } else {
      this.textContent = 'FOLLOWING';
      this.style.backgroundColor = '#fff';
      this.style.color = '#333';
      this.style.border = '1px solid #ccc';
  }
});

// --- Save/Collection ---
document.getElementById('btnSave')?.addEventListener('click', function() {
  <?php if(!$uid): ?>
  new bootstrap.Modal(document.getElementById('loginModal')).show(); return;
  <?php endif ?>

  const saved=this.dataset.saved==='1';
  this.dataset.saved=saved?'0':'1';
  this.disabled=true;

  fetch('collection_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+this.dataset.id})
    .catch(()=>{});

  let txt = document.getElementById('saveText');
  if(!saved) {
      this.querySelector('i').className='bi bi-collection-fill me-1 fs-5';
      if(txt) txt.textContent='In collections';
  }
  else {
      this.querySelector('i').className='bi bi-plus-lg me-1 fs-5';
      if(txt) txt.textContent='Add To';
  }
  this.disabled=false;
});

// --- Comments ---
async function loadComments(){
  const res=await fetch('comment.php?model_id='+MODEL_ID);
  const d=await res.json();
  const list=document.getElementById('commentList');
  if(!d.comments||!d.comments.length){ list.innerHTML='<p class="text-muted">ยังไม่มีความคิดเห็น</p>'; return; }
  list.innerHTML='';
  d.comments.forEach(c=>{
    const canDel = CUR_UID && (CUR_UID===parseInt(c.user_id));
    const el=document.createElement('div');
    el.className='comment-item'; el.dataset.id=c.id;
    el.innerHTML=`
      <div class="d-flex justify-content-between align-items-start">
        <div class="comment-meta fw-semibold">${escHtml(c.username)} <span class="fw-normal">&mdash; ${c.created_at}</span></div>
        ${canDel?`<button class="btn btn-sm btn-link text-danger p-0 del-comment" data-id="${c.id}"><i class="bi bi-trash3"></i></button>`:''}
      </div>
      <div class="comment-body mt-1">${escHtml(c.body)}</div>`;
    list.appendChild(el);
  });
  document.getElementById('cCount').textContent='('+d.comments.length+')';
}

function escHtml(t){ const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

document.getElementById('commentList')?.addEventListener('click', async e=>{
  const btn=e.target.closest('.del-comment'); if(!btn) return;
  if(!confirm('ลบความคิดเห็นนี้?')) return;
  const res=await fetch('comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=delete&id='+btn.dataset.id});
  const d=await res.json();
  if(d.ok){ btn.closest('.comment-item').remove(); }
});

document.getElementById('commentForm')?.addEventListener('submit', async e=>{
  e.preventDefault();
  const body=document.getElementById('commentBody').value.trim();
  if(!body) return;
  const res=await fetch('comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=add&model_id='+MODEL_ID+'&body='+encodeURIComponent(body)});
  const d=await res.json();
  if(d.ok){ document.getElementById('commentBody').value=''; loadComments(); }
});

loadComments();

// --- Payment System ---
const paymentModalEl = document.getElementById('paymentModal');
if (paymentModalEl) {
  paymentModalEl.addEventListener('show.bs.modal', async () => {
    document.getElementById('qrLoading').classList.remove('d-none');
    document.getElementById('qrError').classList.add('d-none');
    document.getElementById('qrImage').classList.add('d-none');
    document.getElementById('qrPrice').classList.add('d-none');

    try {
      const res = await fetch('promptpay_qr.php?model_id=' + MODEL_ID);
      const data = await res.json();
      document.getElementById('qrLoading').classList.add('d-none');

      const bankInfoEl = document.getElementById('bankInfo');
      const bankNameEl = document.getElementById('bankNameDisplay');
      const bankAccEl = document.getElementById('bankAccountDisplay');
      const bankAccNameEl = document.getElementById('bankAccountNameDisplay');
      const qrImageEl = document.getElementById('qrImage');
      const instructionEl = document.getElementById('paymentInstruction');

      // Reset displays
      qrImageEl.classList.add('d-none');
      bankInfoEl.classList.add('d-none');

      const bankNames = {
        'promptpay': 'พร้อมเพย์ (PromptPay)',
        'truemoney': 'TrueMoney Wallet',
        'kbank': 'ธนาคารกสิกรไทย (KBANK)',
        'scb': 'ธนาคารไทยพาณิชย์ (SCB)',
        'bbl': 'ธนาคารกรุงเทพ (BBL)',
        'ktb': 'ธนาคารกรุงไทย (KTB)',
        'krungsri': 'ธนาคารกรุงศรีอยุธยา (BAY)'
      };

      if (data.error) {
        document.getElementById('qrError').classList.remove('d-none');
      } else if (data.method) {
        if (data.method === 'promptpay' || data.method === 'truemoney') {
            instructionEl.innerHTML = 'สแกน QR Code ด้วยแอปธนาคารหรือ<br>TrueMoney Wallet';
            // Generate QR Code via QRServer API
            qrImageEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(data.payload);
            qrImageEl.classList.remove('d-none');
        } else {
            instructionEl.innerHTML = 'โอนเงินเข้าบัญชีธนาคารด้านล่างนี้';
            bankNameEl.textContent = bankNames[data.method] || data.method;

            const bankColors = {
               'promptpay': { color: '#113566', text: 'PP' },
               'truemoney': { color: '#f68b1f', text: 'TM' },
               'kbank': { color: '#138f2d', text: 'K' },
               'scb': { color: '#4e2e7f', text: 'S' },
               'bbl': { color: '#1e4598', text: 'B' },
               'ktb': { color: '#00aeee', text: 'K' },
               'krungsri': { color: '#fec43b', text: 'K' }
            };
            const bIcon = document.getElementById('bankIconDisplay');
            if(bIcon && bankColors[data.method]) {
               bIcon.style.backgroundColor = bankColors[data.method].color;
               bIcon.textContent = bankColors[data.method].text;
               bIcon.classList.remove('d-none');
            } else if (bIcon) {
               bIcon.classList.add('d-none');
            }

            bankAccEl.textContent = data.account;
            bankAccNameEl.textContent = data.name;
            bankInfoEl.classList.remove('d-none');
        }
        document.getElementById('qrPrice').classList.remove('d-none');
      }
    } catch(err) {
      document.getElementById('qrLoading').classList.add('d-none');
      document.getElementById('qrError').classList.remove('d-none');
    }
  });

  document.getElementById('paymentForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังส่ง...';

    const formData = new FormData(e.target);
    formData.append('action', 'create_order');

    try {
      const res = await fetch('payment.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.ok) {
        alert('ส่งสลิปเรียบร้อยแล้ว รอผู้ขายหรือแอดมินตรวจสอบครับ');
        location.reload();
      } else {
        alert((data.error || 'เกิดข้อผิดพลาด') + (data.detail ? ' : ' + data.detail : ''));
      }
    } catch(err) {
      alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    }

    btn.disabled = false;
    btn.innerHTML = 'ยืนยันการชำระเงิน';
  });
}
</script>
<!-- Download Modal -->
<div class="modal fade" id="downloadModal" tabindex="-1" aria-labelledby="downloadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#fff; color:#111; border-radius:12px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="downloadModalLabel">Available downloads</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="list-group list-group-flush border rounded">
          <?php foreach($dlFiles as $df): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center py-3" style="border-color:#eaeaea;">
            <div>
              <div class="fw-bold" style="font-size:1.1rem;"><?= $df['ext'] ?> <span class="text-muted fw-normal fs-6 ms-2"><?= $df['label'] ?></span></div>
              <div class="text-muted small mt-1">.<?= strtolower($df['ext']) ?> <span class="mx-2">&bull;</span> <?= $df['size'] ?></div>
            </div>
            <a href="download.php?id=<?= $mid ?>&format=<?= $df['format'] ?>" class="btn btn-info btn-sm text-white fw-bold px-3 py-2 rounded-1" target="_blank" style="letter-spacing:0.5px;">DOWNLOAD</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
