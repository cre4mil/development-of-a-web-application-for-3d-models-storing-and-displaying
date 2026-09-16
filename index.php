<?php
require 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$search = trim($_GET['search'] ?? '');
$tag    = trim($_GET['tag'] ?? '');
$license = trim($_GET['license'] ?? '');
$date    = trim($_GET['date'] ?? '');
$sort    = trim($_GET['sort'] ?? '');

$like   = "%$search%";
$uid    = (int)($_SESSION['uid'] ?? 0);

$perPage = 6;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$tagJoin = $tag !== '' ? "JOIN model_tags mt ON mt.model_id=m.id JOIN tags tg ON tg.id=mt.tag_id" : '';

// 1. Build WHERE conditions dynamically
$whereConditions = ["m.is_public=1"];
$whereParams = [];

if ($search !== '') {
  $whereConditions[] = "(m.title LIKE :search OR m.description LIKE :search OR EXISTS (SELECT 1 FROM model_tags mt2 JOIN tags t2 ON t2.id=mt2.tag_id WHERE mt2.model_id=m.id AND t2.name LIKE :search))";
  $whereParams[':search'] = $like;
}

if ($tag !== '') {
  $whereConditions[] = "tg.name = :tag";
  $whereParams[':tag'] = $tag;
}

if ($license !== '') {
  $whereConditions[] = "m.license = :license";
  $whereParams[':license'] = $license;
}

if ($date !== '') {
  if ($date === 'week') {
    $whereConditions[] = "m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  } elseif ($date === 'month') {
    $whereConditions[] = "m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
  } elseif ($date === 'year') {
    $whereConditions[] = "m.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
  }
}

$whereSql = implode(" AND ", $whereConditions);

// 2. Build ORDER BY clause based on sort parameter
$orderBySql = "m.created_at DESC"; // default

if ($sort === 'likes') {
  $orderBySql = "like_count DESC, m.created_at DESC";
} elseif ($sort === 'views') {
  $orderBySql = "m.view_count DESC, m.created_at DESC";
} elseif ($sort === 'recent') {
  $orderBySql = "m.created_at DESC";
} elseif ($sort === 'relevance' && $search !== '') {
  $orderBySql = "CASE WHEN m.title LIKE :search THEN 3 WHEN m.description LIKE :search THEN 2 ELSE 1 END DESC, m.created_at DESC";
} else {
  if ($search !== '') {
    $orderBySql = "CASE WHEN m.title LIKE :search THEN 3 WHEN m.description LIKE :search THEN 2 ELSE 1 END DESC, m.created_at DESC";
  } else {
    $orderBySql = "m.created_at DESC";
  }
}

// Counts query
$cntSql = "SELECT COUNT(DISTINCT m.id) FROM models m JOIN users u ON u.id=m.user_id $tagJoin WHERE $whereSql";
$cst = $pdo->prepare($cntSql);
foreach ($whereParams as $k => $v) {
  $cst->bindValue($k, $v);
}
$cst->execute();
$totalRows  = (int)$cst->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));
if ($offset >= $totalRows) { $page = $totalPages; $offset = ($page-1)*$perPage; }

// Data query
$dataSql = "SELECT DISTINCT m.id,m.title,m.thumb,m.user_id,m.filename,m.description,
                   m.created_at,m.is_public,m.license,m.view_count,u.username AS uploader,
                   (SELECT COUNT(*) FROM likes WHERE model_id=m.id) AS like_count,
                   (SELECT COUNT(*) FROM comments WHERE model_id=m.id) AS comment_count
            FROM models m JOIN users u ON u.id=m.user_id $tagJoin
            WHERE $whereSql
            ORDER BY $orderBySql LIMIT :lim OFFSET :off";
$st = $pdo->prepare($dataSql);
foreach ($whereParams as $k => $v) {
  $st->bindValue($k, $v);
}
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$models = $st->fetchAll(PDO::FETCH_ASSOC);

// Tags for displayed models
$modelIds = array_column($models, 'id');
$tagMap = [];
if ($modelIds) {
  $in = implode(',', array_fill(0, count($modelIds), '?'));
  $ts = $pdo->prepare("SELECT mt.model_id, t.name FROM model_tags mt JOIN tags t ON t.id=mt.tag_id WHERE mt.model_id IN ($in)");
  $ts->execute($modelIds);
  foreach ($ts->fetchAll() as $r) $tagMap[$r['model_id']][] = $r['name'];
}

$likedIds = $collectedIds = [];
if ($uid) {
  $st=$pdo->prepare("SELECT model_id FROM likes WHERE user_id=?"); $st->execute([$uid]);
  $likedIds=array_map('intval',array_column($st->fetchAll(PDO::FETCH_ASSOC),'model_id'))?:[];
  $st=$pdo->prepare("SELECT model_id FROM collections WHERE user_id=?"); $st->execute([$uid]);
  $collectedIds=array_map('intval',array_column($st->fetchAll(PDO::FETCH_ASSOC),'model_id'))?:[];
}

// Fetch all tags and licenses for the dropdowns
$allTags = $pdo->query("SELECT name FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$allLicenses = ['CC BY', 'CC BY-SA', 'CC BY-ND', 'CC BY-NC', 'CC BY-NC-SA', 'CC BY-NC-ND', 'CC0', 'All Rights Reserved'];

function human_filesize($b){if($b<=0)return'0 B';$u=['B','KB','MB','GB'];$p=floor(log($b,1024));return round($b/pow(1024,$p),2).' '.$u[$p];}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>3D Model Gallery</title>
  <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    .tag-chip{display:inline-block;padding:.15rem .6rem;border-radius:20px;background:rgba(255,191,0,.15);color:#fbbf24;font-size:.75rem;text-decoration:none;margin:.1rem;}
    .tag-chip:hover{background:rgba(255,191,0,.3);color:#fbbf24;}
    .card-stats{display:flex;gap:.8rem;font-size:.78rem;color:#aaa;padding:.3rem .5rem;}
    .card-stats span{display:flex;align-items:center;gap:.25rem;}
    .license-badge-sm{font-size:.65rem;padding:.1rem .45rem;border-radius:10px;background:rgba(255,255,255,.1);color:#ccc;border:1px solid rgba(255,255,255,.1);}
    .btn-bookmark{display:none;}/* ย้าย bookmark เข้า dropdown แล้ว — ซ่อน floating button */
    
    /* Filter Bar Styles (Light Theme) */
    .filter-bar-custom {
      background: #fff;
      border: 1px solid #eaeaea;
      border-radius: 12px;
      padding: 1rem;
      box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }
    .filter-bar-custom select, .filter-bar-custom input {
      background: #fff !important;
      border: 1px solid #dee2e6 !important;
      color: #495057 !important;
      border-radius: 8px !important;
      font-size: 0.85rem !important;
      padding: 0.5rem 0.8rem !important;
      transition: all 0.2s ease;
    }
    .filter-bar-custom select:focus, .filter-bar-custom input:focus {
      background: #fff !important;
      border-color: #ffc107 !important;
      box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2) !important;
      outline: none;
    }
    .filter-bar-custom select option {
      background: #fff;
      color: #333;
    }
    .filter-bar-custom label {
      font-weight: 600;
      font-size: 0.8rem !important;
      color: #6c757d !important;
      margin-bottom: 0.4rem !important;
    }
    .btn-reset-custom {
      border: 1px solid #dee2e6;
      background: #fff;
      color: #6c757d;
      transition: all 0.2s ease;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .btn-reset-custom:hover {
      background: #f8f9fa;
      color: #495057;
    }
  </style>
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="index.php" class="logo">
      <span class="logo-icon"><i class="bi bi-box"></i></span>
      3D Gallery
    </a>
    <nav>
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

<div class="container mt-4">
    <!-- Premium Filter & Sort Bar -->
    <form method="get" action="index.php" id="filterForm" class="filter-bar-custom mb-4">
      <div class="row g-3 align-items-end">
        
        <!-- Search Input -->
        <div class="col-md-3">
          <label class="form-label small text-muted fw-bold text-uppercase mb-1"><i class="bi bi-search me-1"></i> ค้นหา</label>
          <div class="input-group">
            <input type="search" name="search" class="form-control" placeholder="ค้นหาชื่อ, รายละเอียด หรือแท็ก..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-warning" type="submit"><i class="bi bi-search"></i></button>
          </div>
        </div>
        
        <!-- Category/Tag Dropdown -->
        <div class="col-md-2 col-6">
          <label class="form-label small text-muted text-uppercase mb-1 d-block"><i class="bi bi-tag me-1"></i> หมวดหมู่ (Tag)</label>
          <select name="tag" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">ทั้งหมด (All tags)</option>
            <?php foreach ($allTags as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>" <?= $tag === $t ? 'selected' : '' ?>>#<?= htmlspecialchars($t) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Date Dropdown -->
        <div class="col-md-2 col-6">
          <label class="form-label small text-muted text-uppercase mb-1 d-block"><i class="bi bi-calendar-event me-1"></i> วันที่อัปโหลด</label>
          <select name="date" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="" <?= $date === '' ? 'selected' : '' ?>>ทุกช่วงเวลา (Any time)</option>
            <option value="week" <?= $date === 'week' ? 'selected' : '' ?>>สัปดาห์นี้ (This week)</option>
            <option value="month" <?= $date === 'month' ? 'selected' : '' ?>>เดือนนี้ (This month)</option>
            <option value="year" <?= $date === 'year' ? 'selected' : '' ?>>ปีนี้ (This year)</option>
          </select>
        </div>

        <!-- Licenses Dropdown -->
        <div class="col-md-2 col-6">
          <label class="form-label small text-muted text-uppercase mb-1 d-block"><i class="bi bi-shield-check me-1"></i> สัญญาอนุญาต</label>
          <select name="license" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="" <?= $license === '' ? 'selected' : '' ?>>ทั้งหมด (Any)</option>
            <?php foreach ($allLicenses as $lic): ?>
              <option value="<?= htmlspecialchars($lic) ?>" <?= $license === $lic ? 'selected' : '' ?>><?= htmlspecialchars($lic) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Sort By Dropdown -->
        <div class="col-md-2 col-6">
          <label class="form-label small text-muted text-uppercase mb-1 d-block"><i class="bi bi-sort-down me-1"></i> จัดเรียงโดย</label>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="" <?= $sort === '' ? 'selected' : '' ?>><?= $search !== '' ? 'Relevance (ความเกี่ยวข้อง)' : 'Default (ค่าเริ่มต้น)' ?></option>
            <option value="likes" <?= $sort === 'likes' ? 'selected' : '' ?>>Most liked (ถูกใจมากที่สุด)</option>
            <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most viewed (ยอดชมมากที่สุด)</option>
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most recent (ล่าสุด)</option>
          </select>
        </div>

        <!-- Reset Button -->
        <div class="col-md-1 col-12 text-md-end">
          <a href="index.php" class="btn btn-sm btn-reset-custom w-100 py-1"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        </div>

      </div>
    </form>
  <?php if (!empty($_SESSION['upload_error'])): ?>
    <div class="alert alert-danger mt-3"><?= htmlspecialchars($_SESSION['upload_error']) ?></div>
    <?php unset($_SESSION['upload_error']); endif; ?>
  <?php if (isset($_GET['uploads']) && $_GET['uploads']==='1'): ?>
    <div class="alert alert-success mt-3">อัปโหลดสำเร็จแล้ว!</div>
  <?php endif; ?>


  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 g-4" id="model-grid">
    <?php foreach ($models as $m): 
      $mid = (int)$m['id'];
      $mTags = $tagMap[$mid] ?? [];
      $isLiked = in_array($mid, $likedIds);
      $isSaved = in_array($mid, $collectedIds);
      $license = htmlspecialchars($m['license'] ?: 'All Rights Reserved');
      
      $uploader = htmlspecialchars($m['uploader']);
      $desc = htmlspecialchars($m['description']);
      $file = 'uploads/'.htmlspecialchars($m['filename']);
      $dateText = date('d/m/Y H:i', strtotime($m['created_at']));
      
      $p = 'uploads/'.$m['filename'];
      $ext = strtolower(pathinfo($m['filename'], PATHINFO_EXTENSION));
      $sizeText = file_exists($p) ? human_filesize(filesize($p)) : 'Unknown';
    ?>
    <div class="col">
      <div class="card h-100 border-0 shadow-sm" style="border-radius:12px; overflow:hidden; background-color: #fff; transition: transform 0.2s ease, box-shadow 0.2s ease;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 10px 20px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 6px rgba(0,0,0,0.02)';" onfocusin="this.style.transform='translateY(-4px)';this.style.boxShadow='0 10px 20px rgba(0,0,0,0.1)';" onfocusout="this.style.transform='none';this.style.boxShadow='0 4px 6px rgba(0,0,0,0.02)';">
        
        <!-- Top Action Buttons (Floating over image) -->
        <div class="position-absolute top-0 end-0 p-2 d-flex flex-column gap-2" style="z-index: 10;">
          <!-- Dropdown Menu -->
          <div class="dropdown">
            <button class="btn btn-sm btn-light shadow-sm" style="width: 32px; height: 32px; border-radius: 50%; opacity: 0.9;" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="font-size: 0.9rem;">
              <li><a class="dropdown-item fw-bold" href="model.php?id=<?= $mid ?>"><i class="bi bi-box-arrow-up-right me-2"></i>ดูรายละเอียดโมเดล</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a href="#" class="dropdown-item open-viewer"
                   data-id="<?= $mid ?>" data-title="<?= htmlspecialchars($m['title']) ?>"
                   data-desc="<?= $desc ?>" data-file="<?= $file ?>" data-ext="<?= $ext ?>"
                   data-uploader="<?= $uploader ?>" data-date="<?= $dateText ?>" data-size="<?= $sizeText ?>"
                   data-license="<?= $license ?>" data-views="<?= (int)$m['view_count'] ?>"
                   data-likes="<?= (int)$m['like_count'] ?>" data-liked="<?= $isLiked?1:0 ?>"
                   data-saved="<?= $isSaved?1:0 ?>" data-tags="<?= htmlspecialchars(json_encode($mTags), ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-box me-2"></i>ดูโมเดล 3D ที่นี่
              </a></li>
              <?php if($uid): ?>
              <li><a href="#" class="dropdown-item bookmark-toggle <?= $isSaved?'text-warning':'' ?>" data-id="<?= $mid ?>">
                <i class="bi <?= $isSaved?'bi-bookmark-fill':'bi-bookmark' ?> me-2"></i><?= $isSaved?'บันทึกแล้ว':'บันทึก' ?></a></li>
              <?php endif ?>
              <?php if($uid === (int)$m['user_id']): ?>
              <li><hr class="dropdown-divider"></li>
              <li><a href="#" class="dropdown-item open-edit" data-id="<?= $mid ?>"><i class="bi bi-pencil me-2"></i>แก้ไข</a></li>
              <li><a href="#" class="dropdown-item js-toggle-public" data-id="<?= $mid ?>" data-public="<?= (int)$m['is_public'] ?>"><?= $m['is_public']?'<i class="bi bi-eye-slash me-2"></i>ซ่อน':'<i class="bi bi-eye me-2"></i>แสดง' ?></a></li>
              <li><a href="#" class="dropdown-item text-danger delete-model" data-id="<?= $mid ?>" data-title="<?= htmlspecialchars($m['title']) ?>"><i class="bi bi-trash3 me-2"></i>ลบ</a></li>
              <?php endif ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="download.php?id=<?= $mid ?>"><i class="bi bi-download me-2"></i>ดาวน์โหลด</a></li>
            </ul>
          </div>
          
          <!-- Like Button -->
          <button class="btn btn-sm shadow-sm btn-like-toggle <?= $isLiked?'btn-danger text-white':'btn-light text-muted' ?>" style="width: 32px; height: 32px; border-radius: 50%; opacity: 0.9;" data-id="<?= $mid ?>" data-liked="<?= $isLiked?1:0 ?>">
            <i class="bi <?= $isLiked?'bi-heart-fill':'bi-heart' ?>"></i>
          </button>
        </div>

        <!-- Thumbnail Image -->
        <a href="model.php?id=<?= $mid ?>" class="d-block" style="aspect-ratio: 4/3; background-color: #f8f9fa; overflow: hidden; cursor: pointer;">
          <img src="uploads/<?= htmlspecialchars($m['thumb'] ?? '') ?>" alt="<?= htmlspecialchars($m['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
        </a>

        <!-- Card Body -->
        <div class="card-body p-3 d-flex flex-column">
          <!-- Title & Uploader -->
          <div class="d-flex align-items-center mb-2">
            <div class="me-2" style="width: 32px; height: 32px; background: #e0e0e0; color: #555; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.85rem; font-weight: bold; flex-shrink: 0;">
              <?= mb_strtoupper(mb_substr($uploader,0,1,'UTF-8')) ?>
            </div>
            <div class="text-truncate">
              <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                <a href="model.php?id=<?= $mid ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($m['title']) ?></a>
              </h6>
              <div class="text-muted small text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($uploader) ?></div>
            </div>
          </div>
          
          <!-- Tags -->
          <?php if($mTags): ?>
          <div class="mb-2 text-truncate">
            <?php foreach($mTags as $t): ?><a href="index.php?tag=<?= urlencode($t) ?>" class="text-decoration-none text-muted" style="font-size: 0.7rem; margin-right: 0.3rem;">#<?= htmlspecialchars($t) ?></a><?php endforeach ?>
          </div>
          <?php endif ?>
          
          <div class="mt-auto pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
            <div class="d-flex gap-3">
              <span><i class="bi bi-eye"></i> <?= number_format((int)$m['view_count']) ?></span>
              <span><i class="bi bi-chat-square-text"></i> <?= (int)$m['comment_count'] ?></span>
              <span><i class="bi bi-heart"></i> <span class="like-counter-val"><?= (int)$m['like_count'] ?></span></span>
            </div>
            <div class="fw-bold" style="font-size: 0.65rem; padding: 2px 5px; background: #f1f1f1; border-radius: 4px;"><?= $license ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach ?>

    <?php if (empty($models)): ?>
      <p class="no-models">ยังไม่มีโมเดลในระบบ</p>
    <?php endif ?>
  </div>
</div>

<?php include 'view.php'; ?>

<div class="pagination-section text-center my-4">
  <nav><ul class="pagination justify-content-center" id="pagination-nav">
    <?php
      function build_query($extra=[]){$p=$_GET;unset($p['page']);$p=array_merge($p,$extra);return'index.php?'.http_build_query($p);}
      $prev=max(1,$page-1); $next=min($totalPages,$page+1);
    ?>
    <li class="page-item <?= $page<=1?'disabled':'' ?>"><button class="page-link" data-target="<?= build_query(['page'=>1]) ?>">&laquo;</button></li>
    <li class="page-item <?= $page<=1?'disabled':'' ?>"><button class="page-link" data-target="<?= build_query(['page'=>$prev]) ?>">&lsaquo;</button></li>
    <li class="page-item disabled"><span class="page-link">หน้า <?= $page ?> / <?= $totalPages ?></span></li>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><button class="page-link" data-target="<?= build_query(['page'=>$next]) ?>">&rsaquo;</button></li>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><button class="page-link" data-target="<?= build_query(['page'=>$totalPages]) ?>">&raquo;</button></li>
  </ul></nav>
</div>

<footer class="footer"><div class="footer-inner">
  <div class="footer-left"><h4>Panisara Kunkam</h4><p>Thailand : +66949491035</p><p>Email : <a href="mailto:66160026@go.buu.ac.th">66160026@go.buu.ac.th</a></p></div>
  <div class="footer-right"><h4>© 2026 – ITDI-Informatics-Burapha University</h4></div>
</div></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/GLTFLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/OBJLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/helpers/VertexNormalsHelper.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/FBXLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/STLLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/PLYLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/ColladaLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/TDSLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/libs/fflate.min.js"></script>
<script>
const modelModalEl = document.getElementById('modelModal');
const modelModal   = new bootstrap.Modal(modelModalEl);
const container    = document.getElementById('viewer');
const scene=new THREE.Scene(); scene.background=new THREE.Color(0x333333);
const camera=new THREE.PerspectiveCamera(40,1,0.01,10000);
const renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});
renderer.setPixelRatio(Math.min(devicePixelRatio,2));
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.0;
renderer.outputEncoding = THREE.sRGBEncoding;
container.appendChild(renderer.domElement);
scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dir=new THREE.DirectionalLight(0xffffff,1.3); dir.position.set(5,6,7); scene.add(dir);
const controls=new THREE.OrbitControls(camera,renderer.domElement); controls.enableDamping=true;
let currentModel=null, pendingDataset=null;

let modalOriginalMaterials = new Map();
let modalCurrentMode = 'final';
let modalNormalHelpers = [];
let modalWireframeColor = 'default';

const textureLoader = new THREE.TextureLoader();
const modalUvTexture = textureLoader.load('https://threejs.org/examples/textures/uv_grid_opengl.jpg');
modalUvTexture.wrapS = THREE.RepeatWrapping; modalUvTexture.wrapT = THREE.RepeatWrapping; modalUvTexture.repeat.set(1, 1);

function createMatcap() {
  const canvas = document.createElement('canvas'); canvas.width = 256; canvas.height = 256;
  const ctx = canvas.getContext('2d');
  const cx = 128, cy = 128, r = 126;
  const gradient = ctx.createRadialGradient(cx-30, cy-30, 10, cx, cy, r);
  gradient.addColorStop(0, '#ffffff'); gradient.addColorStop(0.5, '#aaaaaa'); gradient.addColorStop(1, '#222222');
  ctx.fillStyle = gradient; ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
  return new THREE.CanvasTexture(canvas);
}
const modalMatcapTexture = createMatcap();

function storeModalOriginalMaterials(obj) {
  modalOriginalMaterials.clear();
  obj.traverse(child => {
    if (child.isMesh && child.material) {
      modalOriginalMaterials.set(child, Array.isArray(child.material) ? child.material.map(m => m.clone()) : child.material.clone());
    }
  });
}

function setModalInspectorMode(mode) {
  if (!currentModel) return;
  modalCurrentMode = mode;
  
  if (mode === 'noPost') {
    renderer.toneMapping = THREE.NoToneMapping;
  } else {
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
  }
  
  currentModel.traverse(child => {
    if (child.isMesh) {
      const origMat = modalOriginalMaterials.get(child);
      if (!origMat) return;
      
      const processMaterial = (mat) => {
        const isSingleSided = document.getElementById('modalSingleSidedToggle')?.checked;
        const currentSide = isSingleSided ? THREE.FrontSide : (mat.side || THREE.FrontSide);
        if (mode === 'final' || mode === 'noPost') {
          const m = mat.clone(); m.side = currentSide; return m;
        }
        
        let map = null, color = 0xcccccc;
        if (mode === 'baseColor') { map = mat.map; color = map ? 0xffffff : (mat.color || 0xcccccc); }
        else if (mode === 'metalness') { map = mat.metalnessMap; color = map ? 0xffffff : new THREE.Color(mat.metalness||0, mat.metalness||0, mat.metalness||0); }
        else if (mode === 'roughness') { map = mat.roughnessMap; color = map ? 0xffffff : new THREE.Color(mat.roughness||1, mat.roughness||1, mat.roughness||1); }
        else if (mode === 'emission') { map = mat.emissiveMap; color = map ? 0xffffff : (mat.emissive || 0x000000); }
        else if (mode === 'normalMap') { map = mat.normalMap; color = map ? 0xffffff : 0x8080ff; }
        else if (mode === 'specular') { color = mat.specular || new THREE.Color(0x333333); }
        else if (mode === 'vertexColor') { return new THREE.MeshBasicMaterial({ vertexColors: true, side: currentSide }); }
        else if (mode === 'matcap') { return new THREE.MeshMatcapMaterial({ matcap: modalMatcapTexture, side: currentSide }); }
        else if (mode === 'matcapSurface') { return new THREE.MeshMatcapMaterial({ matcap: modalMatcapTexture, normalMap: mat.normalMap, normalScale: mat.normalScale, side: currentSide }); }
        else if (mode === 'uv') { map = modalUvTexture; color = 0xffffff; }
        else if (mode === 'wireframe' || mode === 'normals') { return new THREE.MeshBasicMaterial({ color: 0x444444, side: currentSide }); }
        
        return new THREE.MeshBasicMaterial({ map: map, color: color, side: currentSide, transparent: mat.transparent, opacity: mat.opacity });
      };

      if (Array.isArray(origMat)) child.material = origMat.map(processMaterial);
      else child.material = processMaterial(origMat);
      child.material.needsUpdate = true;
    }
  });
  updateModalWireframeOverlay();
  updateModalNormalsOverlay();
}

function updateModalWireframeOverlay() {
  if (!currentModel) return;
  currentModel.traverse(child => {
     if (child.isMesh) {
       if (child.userData.wireframeHelper) {
          child.remove(child.userData.wireframeHelper);
          child.userData.wireframeHelper.geometry.dispose();
          child.userData.wireframeHelper.material.dispose();
          delete child.userData.wireframeHelper;
       }
       if (modalCurrentMode === 'wireframe') {
          const c = modalWireframeColor === 'default' ? '#00ff00' : modalWireframeColor;
          const wireMat = new THREE.LineBasicMaterial({ color: new THREE.Color(c), depthTest: true, transparent: true, opacity: 0.8 });
          const wireGeo = new THREE.WireframeGeometry(child.geometry);
          const wireMesh = new THREE.LineSegments(wireGeo, wireMat);
          child.add(wireMesh);
          child.userData.wireframeHelper = wireMesh;
       }
     }
  });
}

function updateModalNormalsOverlay() {
  if (!currentModel) return;
  modalNormalHelpers.forEach(h => scene.remove(h));
  modalNormalHelpers = [];
  if (modalCurrentMode === 'normals' && THREE.VertexNormalsHelper) {
      currentModel.traverse(child => {
         if (child.isMesh) {
            const h = new THREE.VertexNormalsHelper(child, 0.5, 0x00ff00);
            scene.add(h);
            modalNormalHelpers.push(h);
         }
      });
  }
}

document.getElementById('modalSingleSidedToggle')?.addEventListener('change', (e) => {
  if (!currentModel) return;
  setModalInspectorMode(modalCurrentMode);
});
document.querySelectorAll('.modal-swatch').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.modal-swatch').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    modalWireframeColor = this.dataset.color;
    document.querySelectorAll('.modal-inspector-btn').forEach(b => b.classList.remove('active'));
    const wfBtn = document.querySelector('.modal-inspector-btn[data-mode="wireframe"]');
    if(wfBtn) wfBtn.classList.add('active');
    setModalInspectorMode('wireframe');
  });
});
document.getElementById('btnToggleInspectorModal')?.addEventListener('click', () => {
  document.getElementById('modelInspectorModal').classList.toggle('active');
});
document.getElementById('btnCloseInspectorModal')?.addEventListener('click', () => {
  document.getElementById('modelInspectorModal').classList.remove('active');
});
document.getElementById('btnFullscreenModal')?.addEventListener('click', () => {
  const wrap = document.getElementById('modalViewerWrap');
  if (!document.fullscreenElement) wrap.requestFullscreen().catch(()=>{});
  else document.exitFullscreen();
});
document.querySelectorAll('.modal-inspector-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.modal-inspector-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    
    // Auto-update swatch if wireframe mode clicked
    if (this.dataset.mode === 'wireframe' && modalWireframeColor === 'default') {
      modalWireframeColor = '#cccccc';
      document.querySelectorAll('.modal-swatch').forEach(b => b.classList.remove('active'));
      document.querySelector('.modal-swatch[data-color="#cccccc"]')?.classList.add('active');
    }
    
    setModalInspectorMode(this.dataset.mode);
  });
});

// --- Toolbar Logic ---
document.querySelector('#modalViewerWrap .viewer-toolbar-btn[title="Settings"]')?.addEventListener('click', function() {
  const isHD = this.classList.contains('active');
  this.classList.toggle('active');
  renderer.setPixelRatio(isHD ? 1 : window.devicePixelRatio);
  const badge = this.querySelector('.badge');
  if(badge) badge.textContent = isHD ? 'SD' : 'HD';
});

if (navigator.xr) renderer.xr.enabled = true;
document.querySelector('#modalViewerWrap .viewer-toolbar-btn[title="VR"]')?.addEventListener('click', () => {
  if(navigator.xr) {
    navigator.xr.requestSession('immersive-vr').then(session => renderer.xr.setSession(session)).catch(e => alert('VR Not Supported: ' + e.message));
  } else {
    alert('WebXR not supported in this browser.');
  }
});


function resizeViewer(){const wrap=document.querySelector('.viewer-wrap');const w=wrap.clientWidth,h=wrap.clientHeight||400;renderer.setSize(w,h,false);camera.aspect=w/h||1;camera.updateProjectionMatrix();}
new ResizeObserver(resizeViewer).observe(document.querySelector('.viewer-wrap'));
modelModalEl.addEventListener('shown.bs.modal',()=>{resizeViewer();if(pendingDataset){const ds=pendingDataset;pendingDataset=null;requestAnimationFrame(()=>loadModel(ds.file,(ds.ext||'').toLowerCase()));}});
modelModalEl.addEventListener('hidden.bs.modal',()=>{if(currentModel){scene.remove(currentModel);currentModel.traverse(o=>{o.geometry?.dispose?.();(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m?.dispose?.());});currentModel=null;} if(window.updateModelInfo) window.updateModelInfo(null);});

function fitCamera(obj){const box=new THREE.Box3().setFromObject(obj);const sz=box.getSize(new THREE.Vector3()),c=box.getCenter(new THREE.Vector3());const ms=Math.max(sz.x,sz.y,sz.z)||1,fov=THREE.MathUtils.degToRad(camera.fov);const d=Math.max(ms/(2*Math.tan(fov/2)),ms/(2*Math.tan(fov/2)/camera.aspect))*1.35;camera.near=d/100;camera.far=d*100;camera.updateProjectionMatrix();camera.position.copy(c.clone().add(new THREE.Vector3(d,d,d)));controls.target.copy(c);controls.update();}

function loadModel(file,ext){
  if(currentModel){scene.remove(currentModel);currentModel.traverse(o=>{o.geometry?.dispose?.();(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m?.dispose?.());});currentModel=null;}
  const url=encodeURI(file);
  document.querySelectorAll('.modal-inspector-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.modal-inspector-btn[data-mode="final"]')?.classList.add('active');
  if(ext==='glb'||ext==='gltf'){const L=window.GLTFLoader?new GLTFLoader():new THREE.GLTFLoader();L.load(url,g=>{currentModel=g.scene||g.scenes?.[0]||g;scene.add(currentModel);fitCamera(currentModel);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='obj'){const L=window.OBJLoader?new OBJLoader():new THREE.OBJLoader();L.load(url,obj=>{currentModel=obj;scene.add(obj);fitCamera(obj);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='fbx'){const L=new THREE.FBXLoader();L.load(url,obj=>{currentModel=obj;scene.add(obj);fitCamera(obj);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='stl'){const L=new THREE.STLLoader();L.load(url,geo=>{const mat=new THREE.MeshStandardMaterial({color:0xcccccc,metalness:0.3,roughness:0.6});const mesh=new THREE.Mesh(geo,mat);currentModel=mesh;scene.add(mesh);fitCamera(mesh);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='ply'){const L=new THREE.PLYLoader();L.load(url,geo=>{geo.computeVertexNormals();const mat=new THREE.MeshStandardMaterial({color:0xcccccc,metalness:0.3,roughness:0.6,vertexColors:geo.hasAttribute('color')});const mesh=new THREE.Mesh(geo,mat);currentModel=mesh;scene.add(mesh);fitCamera(mesh);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='dae'){const L=new THREE.ColladaLoader();L.load(url,col=>{currentModel=col.scene;scene.add(col.scene);fitCamera(col.scene);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else if(ext==='3ds'){const L=new THREE.TDSLoader();L.load(url,obj=>{currentModel=obj;scene.add(obj);fitCamera(obj);if(window.updateModelInfo)window.updateModelInfo(currentModel);storeModalOriginalMaterials(currentModel);},undefined,()=>alert('โหลดไม่สำเร็จ'));}
  else alert('ไม่รองรับไฟล์นี้');
}

function openViewer(ds){
  document.getElementById('viewerTitle').textContent=ds.title||'';
  
  // Sketchfab Modal Layout Fields
  if(document.getElementById('viewerUploaderAvatar')) {
    const u = ds.uploader||'U';
    document.getElementById('viewerUploaderAvatar').textContent = u.charAt(0).toUpperCase();
  }
  if(document.getElementById('viewerUploaderName')) document.getElementById('viewerUploaderName').textContent = ds.uploader||'-';
  if(document.getElementById('modalViewCount')) document.getElementById('modalViewCount').textContent = Number(ds.views||0).toLocaleString();
  if(document.getElementById('modalLikeCount')) document.getElementById('modalLikeCount').textContent = Number(ds.likes||0).toLocaleString();
  if(document.getElementById('modalDownloadCount')) document.getElementById('modalDownloadCount').textContent = '0'; // placeholder if no DL count
  
  const descEl = document.getElementById('viewerDesc');
  if(descEl) descEl.textContent = ds.desc||'';
  
  if(document.getElementById('modalLicenseText')) document.getElementById('modalLicenseText').textContent = ds.license||'CC BY';
  if(document.getElementById('modalPublishDate')) document.getElementById('modalPublishDate').textContent = ds.date||'-';
  if(document.getElementById('modalSizeText')) document.getElementById('modalSizeText').textContent = ds.size||'-';
  
  // Tags
  const tagsList = document.getElementById('modalTagsList');
  if(tagsList) {
    tagsList.innerHTML = '';
    try {
      const tags = JSON.parse(ds.tags||'[]');
      tags.forEach(t => {
        const a = document.createElement('a');
        a.href = 'index.php?tag=' + encodeURIComponent(t);
        a.className = 'tag-chip text-decoration-none bg-light text-secondary px-2 py-1 rounded small border';
        a.textContent = '#' + t;
        tagsList.appendChild(a);
      });
    } catch(e) {}
  }
  
  // Action Buttons
  const dlBtn = document.getElementById('viewerDownload');
  if(dlBtn) dlBtn.href = 'download.php?id='+(ds.id||'');
  
  const saveBtn = document.getElementById('modalBtnSave');
  if(saveBtn) {
    saveBtn.dataset.id = ds.id;
    saveBtn.dataset.saved = ds.saved;
    if(ds.saved === '1') {
      saveBtn.innerHTML = '<i class="bi bi-bookmark-fill"></i> Saved';
    } else {
      saveBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add To';
    }
  }
  
  const likeBtn = document.getElementById('modalBtnLike');
  if(likeBtn) {
    likeBtn.dataset.id = ds.id;
    likeBtn.dataset.liked = ds.liked;
    if(ds.liked === '1') {
      likeBtn.classList.add('liked');
      likeBtn.innerHTML = '<i class="bi bi-heart-fill"></i> <span id="modalLikeCount">'+Number(ds.likes||0).toLocaleString()+'</span>';
    } else {
      likeBtn.classList.remove('liked');
      likeBtn.innerHTML = '<i class="bi bi-heart"></i> <span id="modalLikeCount">'+Number(ds.likes||0).toLocaleString()+'</span>';
    }
  }

  // Embed URL
  const embedCode = document.getElementById('modalEmbedCode');
  if(embedCode) {
    const currentHost = window.location.host;
    const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    embedCode.value = `<iframe title="${ds.title||''}" src="http://${currentHost}${currentPath}/model.php?id=${ds.id}&embed=1" width="640" height="480" frameborder="0" allow="autoplay; fullscreen; xr-spatial-tracking" allowfullscreen></iframe>`;
  }
  
  // Share URL
  const shareLink = document.getElementById('modalShareLink');
  if(shareLink) {
    const currentHost = window.location.host;
    const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const link = `http://${currentHost}${currentPath}/model.php?id=${ds.id}`;
    shareLink.value = link;
    document.getElementById('modalShareFb').href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`;
    document.getElementById('modalShareTw').href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(link)}&text=${encodeURIComponent(ds.title||'')}`;
    document.getElementById('modalShareLine').href = `https://line.me/R/msg/text/?${encodeURIComponent((ds.title||'') + ' ' + link)}`;
  }

  const mpLink=document.getElementById('viewerModelPage');
  if(mpLink) mpLink.href='model.php?id='+(ds.id||'');
  
  if(window._loadModalComments) window._loadModalComments(ds.id);
  pendingDataset=ds; modelModal.show();
}

document.addEventListener('click',e=>{const t=e.target.closest('.open-viewer');if(!t)return;e.preventDefault();openViewer(t.dataset);});

// Like
document.querySelectorAll('.btn-like-toggle').forEach(btn => {
    btn.addEventListener('click', async function(e){
      e.preventDefault(); e.stopPropagation();
      <?php if(!$uid): ?>
      new bootstrap.Modal(document.getElementById('loginModal')).show(); return;
      <?php endif ?>
      this.disabled = true;
      const id = this.dataset.id;
      const res = await fetch('like_action.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+id});
      const data = await res.json();
      
      const isLiked = this.dataset.liked === '1';
      this.dataset.liked = isLiked ? '0' : '1';
      
      const icon = this.querySelector('i');
      let valSpan = this.closest('.card').querySelector('.like-counter-val');
      
      if (!isLiked) {
        this.classList.remove('btn-light', 'text-muted');
        this.classList.add('btn-danger', 'text-white');
        icon.className = 'bi bi-heart-fill';
        if(valSpan) valSpan.textContent = parseInt(valSpan.textContent) + 1;
      } else {
        this.classList.remove('btn-danger', 'text-white');
        this.classList.add('btn-light', 'text-muted');
        icon.className = 'bi bi-heart';
        if(valSpan) valSpan.textContent = Math.max(0, parseInt(valSpan.textContent) - 1);
      }
      this.disabled = false;
    });
  });

// Bookmark / Collection
document.addEventListener('click',async e=>{
  const btn=e.target.closest('.bookmark-toggle');if(!btn)return;e.preventDefault();
  if(btn.tagName==='A') btn.style.pointerEvents='none';
  else btn.disabled=true;
  const res=await fetch('collection.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'model_id='+btn.dataset.id});
  const d=await res.json();
  const ic=btn.querySelector('i');
  if(d?.saved){
    btn.classList.add('text-warning');
    if(ic) ic.className='bi bi-bookmark-fill me-1';
    btn.title='บันทึกแล้ว';
    if(btn.tagName==='A') btn.innerHTML=`<i class="bi bi-bookmark-fill me-1"></i>บันทึกแล้ว`;
  } else {
    btn.classList.remove('text-warning');
    if(ic) ic.className='bi bi-bookmark me-1';
    btn.title='บันทึก';
    if(btn.tagName==='A') btn.innerHTML=`<i class="bi bi-bookmark me-1"></i>บันทึก`;
  }
  if(btn.tagName==='A') btn.style.pointerEvents='';
  else btn.disabled=false;
});


// Toggle public
document.addEventListener('click',async e=>{
  const btn=e.target.closest('.js-toggle-public');if(!btn)return;e.preventDefault();
  const id=btn.dataset.id,cur=Number(btn.dataset.public)||0,nxt=cur?0:1;btn.disabled=true;
  const res=await fetch('toggle_visibility.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({id,is_public:nxt})});
  const d=await res.json();
  if(d?.ok){btn.dataset.public=String(nxt);btn.textContent=nxt?'ไม่แสดงบนหน้าหลัก':'แสดงบนหน้าหลัก';}
  btn.disabled=false;
});

// Edit
const editModal=new bootstrap.Modal(document.getElementById('editModal'));
document.addEventListener('click',async e=>{
  const a=e.target.closest('.open-edit');if(!a)return;e.preventDefault();
  const res=await fetch('edit.php?action=get&id='+encodeURIComponent(a.dataset.id));
  if(!res.ok){alert('โหลดไม่สำเร็จ');return;}
  const data=await res.json();
  window.openEditModal(data);
});

// Delete
(()=>{
  const modalEl=document.getElementById('confirmDeleteModal');
  if(!modalEl)return;
  const modal=new bootstrap.Modal(modalEl);
  const titleEl=document.getElementById('cdm-title');
  const confirmBtn=document.getElementById('cdm-confirm');
  let delCtx=null;
  document.addEventListener('click',e=>{
    const btn=e.target.closest('.delete-model');if(!btn)return;e.preventDefault();
    delCtx={id:btn.dataset.id,title:btn.dataset.title||'',anchor:btn};
    titleEl.textContent=delCtx.title; modal.show();
  });
  confirmBtn.addEventListener('click',async()=>{
    if(!delCtx)return;
    const fd=new URLSearchParams({action:'delete',id:delCtx.id,csrf:'<?= htmlspecialchars($_SESSION['csrf']??'',ENT_QUOTES,'UTF-8') ?>'});
    const res=await fetch('edit.php',{method:'POST',body:fd});
    const d=await res.json();
    if(d?.ok){delCtx.anchor.closest('.card')?.remove();modal.hide();}
    else alert(d?.message||'ลบไม่สำเร็จ');
    delCtx=null;
  });
})();

// Pagination
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('#pagination-nav .page-link').forEach(btn=>{
    btn.addEventListener('click',e=>{const url=e.currentTarget.dataset.target;if(url&&!e.currentTarget.closest('.disabled'))window.location.href=url;});
  });
  
  <?php if (!empty($_SESSION['reg_success'])): ?>
    alert('<?= addslashes($_SESSION['reg_success']) ?>');
    const m = new bootstrap.Modal(document.getElementById('loginModal'));
    m.show();
    <?php unset($_SESSION['reg_success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['reg_error'])): ?>
    alert('<?= addslashes($_SESSION['reg_error']) ?>');
    const rm = new bootstrap.Modal(document.getElementById('registerModal'));
    rm.show();
    <?php unset($_SESSION['reg_error']); ?>
  <?php endif; ?>
});

(function animate(){requestAnimationFrame(animate);controls.update();renderer.render(scene,camera);})();
</script>
</body>
</html>
