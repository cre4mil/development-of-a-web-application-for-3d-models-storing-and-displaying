<?php
// ============================================================
// PROFILE.PHP — v2
// เพิ่มเติมจาก v1: Tab "บันทึกไว้" (collections) + Tab "สถิติ"
// v1 เดิมอยู่ใน comment block ด้านล่างสุดของไฟล์นี้
// ============================================================
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}

$uid   = (int)$_SESSION['uid'];
$uname = htmlspecialchars($_SESSION['uname'] ?? 'User', ENT_QUOTES, 'UTF-8');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// --- จัดการบันทึกช่องทางรับเงิน (ถูกย้ายไปที่ creator_earnings.php) ---

function humanFilesize($b){
    if ($b <= 0) {
        return '0 B';
    }
    $u = ['B','KB','MB','GB'];
    $p = floor(log($b, 1024));
    return round($b / pow(1024, $p), 2) . ' ' . $u[$p];
}

// ── Tab 1: โมเดลของฉัน ───────────────────────────────────────
try {
  $st = $pdo->prepare("
    SELECT m.id,m.title,m.filename,m.description,m.created_at,m.license,
           COALESCE(m.is_public,1) AS is_public,
           EXISTS(SELECT 1 FROM collections c WHERE c.user_id=:uid2 AND c.model_id=m.id) AS is_collected
    FROM models m WHERE m.user_id=:uid1 ORDER BY m.created_at DESC");
  $st->execute(['uid1'=>$uid,'uid2'=>$uid]);
  $myModels = $st->fetchAll() ?: [];
} catch(Throwable $e){ $myModels=[]; }

// ── Tab 2: บันทึกไว้ (collections) ───────────────────────────
try {
  $st = $pdo->prepare("
    SELECT m.id,m.title,m.thumb,m.filename,m.description,m.created_at,m.license,u.username AS uploader
    FROM models m
    JOIN collections col ON col.model_id=m.id
    JOIN users u ON u.id=m.user_id
    WHERE col.user_id=? AND m.is_public=1
    ORDER BY col.created_at DESC");
  $st->execute([$uid]);
  $savedModels = $st->fetchAll() ?: [];
} catch(Throwable $e){ $savedModels=[]; }

// ── Tab 3: สถิติ ──────────────────────────────────────────────
try {
  $st = $pdo->prepare("
    SELECT m.id,m.title,m.view_count,m.is_public,m.license,m.created_at,
           (SELECT COUNT(*) FROM likes    WHERE model_id=m.id) AS like_count,
           (SELECT COUNT(*) FROM comments WHERE model_id=m.id) AS comment_count
    FROM models m WHERE m.user_id=? ORDER BY m.view_count DESC");
  $st->execute([$uid]);
  $stats = $st->fetchAll() ?: [];
  $totalViews    = array_sum(array_column($stats,'view_count'));
  $totalLikes    = array_sum(array_column($stats,'like_count'));
  $totalComments = array_sum(array_column($stats,'comment_count'));
} catch(Throwable $e){ $stats=[]; $totalViews=$totalLikes=$totalComments=0; }

// ── Wallet summary (for profile sidebar) ──────────────────────
try {
  $wSt = $pdo->prepare("SELECT available_balance, total_earned FROM creator_wallets WHERE creator_id = ?");
  $wSt->execute([$uid]);
  $walletSummary = $wSt->fetch() ?: ['available_balance'=>0,'total_earned'=>0];
} catch(Throwable $e){ $walletSummary = ['available_balance'=>0,'total_earned'=>0]; }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>โปรไฟล์ – 3D Gallery</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css?v=<?= file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time() ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
  <style>
    .stat-card{background:#f8f9fa;border:1px solid #dee2e6;border-radius:12px;padding:1.2rem;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.02);}
    .stat-card .num{font-size:2rem;font-weight:700;color:#0d6efd;}
    .stat-card .lbl{color:#6c757d;font-size:.85rem;}
    .license-badge-sm{font-size:.65rem;padding:.2rem .5rem;border-radius:10px;background:#e9ecef;color:#495057;}
    .saved-thumb{width:56px;height:56px;object-fit:cover;border-radius:6px;}
    .tab-nav .nav-link{color:#6c757d; font-weight: 500; padding: 0.75rem 1.25rem; border-radius: 8px;}
    .tab-nav .nav-link:hover{color:#0d6efd; background:#f8f9fa;}
    .tab-nav .nav-link.active{color:#fff;background:#0d6efd;box-shadow:0 4px 6px rgba(13,110,253,0.2);}
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
      <div class="nav-item dropdown d-inline-block">
        <a href="#" class="user-toggle dropdown-toggle" data-bs-toggle="dropdown">
          <span class="avatar-circle"><?= mb_strtoupper(mb_substr($uname,0,1,'UTF-8')) ?></span>
          <span class="d-none d-sm-inline"><?= $uname ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li class="dropdown-header">
            <div class="name fw-bold text-dark"><?= $uname ?></div>
            <div class="meta">เข้าสู่ระบบแล้ว</div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="index.php"><i class="bi bi-house me-1"></i> หน้าหลัก</a></li>
          <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> โปรไฟล์</a></li>
          <li><a class="dropdown-item" href="like.php"><i class="bi bi-heart me-1"></i> โมเดลที่ชอบ</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ</a></li>
        </ul>
      </div>
    </nav>
  </div>
</header>

<div class="container py-4">
  <?php if(isset($_SESSION['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['msg']); ?>
  <?php endif ?>

  <!-- Tab Nav -->
  <ul class="nav nav-pills tab-nav mb-4 gap-2" id="profileTabs">
    <li class="nav-item"><a class="nav-link active" href="#tab-mine"    data-tab="mine">   <i class="bi bi-box-seam me-1"></i>โมเดลของฉัน <span class="badge bg-secondary ms-1"><?= count($myModels) ?></span></a></li>
    <li class="nav-item"><a class="nav-link"        href="#tab-saved"   data-tab="saved">  <i class="bi bi-bookmark me-1"></i>บันทึกไว้ <span class="badge bg-secondary ms-1"><?= count($savedModels) ?></span></a></li>
    <li class="nav-item"><a class="nav-link"        href="#tab-stats"   data-tab="stats">  <i class="bi bi-bar-chart me-1"></i>สถิติ</a></li>

  </ul>

  <!-- Tab 1: โมเดลของฉัน -->
  <div id="tab-mine" class="tab-panel">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">โมเดลของฉัน</h2>
      <span class="text-muted">ทั้งหมด <strong><?= count($myModels) ?></strong> รายการ</span>
    </div>
    <?php if(!$myModels): ?>
      <div class="alert alert-light border text-center">ยังไม่มีโมเดลที่คุณอัปโหลด</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th style="width:50px">No.</th><th>ชื่อโมเดล</th><th class="text-center">License</th><th class="text-center" style="width:160px">สถานะ</th><th class="text-center" style="width:160px">จัดการ</th></tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($myModels as $m):
          $title=$m['title']?:basename($m['filename']);
          $ext=strtolower(pathinfo($m['filename'],PATHINFO_EXTENSION));
          $file='uploads/'.htmlspecialchars($m['filename']);
          $desc=htmlspecialchars($m['description']??'');
          $dateText=$m['created_at']?date('d/m/Y H:i',strtotime($m['created_at'])):'-';
          $abs=__DIR__.'/uploads/'.$m['filename'];
          $sizeText=is_file($abs)?humanFilesize(@filesize($abs)):'-';
        ?>
          <tr data-id="<?= (int)$m['id'] ?>">
            <td><?= $i++ ?></td>
            <td class="fw-semibold">
              <a href="model.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($title) ?></a>
            </td>
            <td class="text-center"><span class="license-badge-sm"><?= htmlspecialchars($m['license']??'CC BY') ?></span></td>
            <td class="text-center">
              <?php if((int)$m['is_public']===1): ?>
                <span class="badge bg-success js-status-badge">แสดงบนหน้าหลัก</span>
              <?php else: ?>
                <span class="badge bg-secondary js-status-badge">ซ่อนจากหน้าหลัก</span>
              <?php endif ?>
            </td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-secondary me-1 js-edit"
                      data-id="<?= (int)$m['id'] ?>" data-title="<?= htmlspecialchars($title) ?>"
                      data-description="<?= $desc ?>" title="แก้ไข"><i class="bi bi-pencil-square"></i></button>
              <a href="#" class="btn btn-sm btn-outline-success js-toggle-public"
                 data-id="<?= (int)$m['id'] ?>" data-public="<?= (int)$m['is_public'] ?>">
                <?= $m['is_public']?'<i class="bi bi-eye-slash"></i>':'<i class="bi bi-eye"></i>' ?>
              </a>
              <button class="btn btn-sm btn-outline-danger js-delete ms-1"
                      data-id="<?= (int)$m['id'] ?>" data-title="<?= htmlspecialchars($title) ?>"
                      data-csrf="<?= $csrf ?>"><i class="bi bi-trash3"></i></button>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>

  <!-- Tab 2: บันทึกไว้ -->
  <div id="tab-saved" class="tab-panel" style="display:none;">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">โมเดลที่บันทึกไว้</h2>
      <span class="text-muted">ทั้งหมด <strong><?= count($savedModels) ?></strong> รายการ</span>
    </div>
    <?php if(!$savedModels): ?>
      <div class="alert alert-light border text-center">ยังไม่มีโมเดลที่บันทึกไว้ <a href="index.php">เริ่มค้นหาโมเดล</a></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th style="width:70px">รูป</th><th>ชื่อโมเดล</th><th>โดย</th><th class="text-center">License</th><th class="text-center" style="width:120px">จัดการ</th></tr>
        </thead>
        <tbody>
        <?php foreach($savedModels as $m): ?>
          <tr data-saved-id="<?= (int)$m['id'] ?>">
            <td><img src="uploads/<?= htmlspecialchars($m['thumb']??'') ?>" class="saved-thumb" alt=""></td>
            <td class="fw-semibold"><a href="model.php?id=<?= (int)$m['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($m['title']) ?></a></td>
            <td class="text-muted small"><?= htmlspecialchars($m['uploader']) ?></td>
            <td class="text-center"><span class="license-badge-sm"><?= htmlspecialchars($m['license']??'CC BY') ?></span></td>
            <td class="text-center">
              <a href="model.php?id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              <button class="btn btn-sm btn-outline-danger js-unsave ms-1" data-id="<?= (int)$m['id'] ?>" title="ลบออกจากรายการ"><i class="bi bi-bookmark-x"></i></button>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>

  <!-- Tab 3: สถิติ -->
  <div id="tab-stats" class="tab-panel" style="display:none;">
    <h2 class="mb-4">สถิติของฉัน</h2>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3"><div class="stat-card"><div class="num"><?= count($myModels) ?></div><div class="lbl"><i class="bi bi-box-seam me-1"></i>โมเดลทั้งหมด</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="num"><?= number_format($totalViews) ?></div><div class="lbl"><i class="bi bi-eye me-1"></i>ยอดเข้าชมรวม</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="num"><?= number_format($totalLikes) ?></div><div class="lbl"><i class="bi bi-heart me-1"></i>ยอด Like รวม</div></div></div>
      <div class="col-6 col-md-3"><div class="stat-card"><div class="num"><?= number_format($totalComments) ?></div><div class="lbl"><i class="bi bi-chat-dots me-1"></i>ความคิดเห็นรวม</div></div></div>
    </div>
    <?php if($stats): ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th>ชื่อโมเดล</th><th class="text-center"><i class="bi bi-eye"></i> Views</th><th class="text-center"><i class="bi bi-heart"></i> Likes</th><th class="text-center"><i class="bi bi-chat-dots"></i> Comments</th><th class="text-center">สถานะ</th></tr>
        </thead>
        <tbody>
        <?php foreach($stats as $s): ?>
          <tr>
            <td><a href="model.php?id=<?= (int)$s['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($s['title']) ?></a></td>
            <td class="text-center"><?= number_format((int)$s['view_count']) ?></td>
            <td class="text-center"><?= (int)$s['like_count'] ?></td>
            <td class="text-center"><?= (int)$s['comment_count'] ?></td>
            <td class="text-center"><?= $s['is_public']?'<span class="badge bg-success">สาธารณะ</span>':'<span class="badge bg-secondary">ซ่อน</span>' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>



<?php include_once 'view.php'; ?>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">ยืนยันการลบโมเดล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">ต้องการลบโมเดล "<span id="cdm-title"></span>" หรือไม่?<br><small class="text-muted">ไฟล์และข้อมูลทั้งหมดจะถูกลบถาวร</small></div>
      <div class="modal-footer"><button type="button" class="btn btn-danger" id="cdm-confirm">ยืนยัน</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/build/three.min.js" integrity="sha384-XIeZcIwWx2i8CVKHEeXtUv7cYAaKNZEqfaxJBdCjo0PcBsE/VWGKsa2SFDKvtW5S" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/GLTFLoader.js" integrity="sha384-+bpKS48ZxfAa8n4kv4SZhJNbgTIxZ0zQ1Y/dqH4hrrHViarGZaihLypNJGSGa/p6" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/OBJLoader.js" integrity="sha384-UWFC8mrevmKCZhKbJ/8/dqLrRAvHArRwJCKjwruJuXyhsebGMFsIK5zrn+R9r+fT" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/controls/OrbitControls.js" integrity="sha384-fcwmxprR7ntks0MLHmwtgWca24P1SKyvyrYWvHvD6ciUjdZ1iqG0YVYpI0KfpwB1" crossorigin="anonymous"></script>

<script>
// Tab switching
document.querySelectorAll('#profileTabs .nav-link').forEach(a=>{
  a.addEventListener('click', e=>{
    e.preventDefault();
    document.querySelectorAll('#profileTabs .nav-link').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(x=>x.style.display='none');
    a.classList.add('active');
    const tab = a.dataset.tab;
    document.getElementById('tab-'+tab).style.display='';
    window.location.hash = 'tab-' + tab;
  });
});

// Auto open tab from hash
if (window.location.hash) {
  const tabId = window.location.hash.replace('#tab-', '');
  const tabLink = document.querySelector(`#profileTabs .nav-link[data-tab="${tabId}"]`);
  if (tabLink) tabLink.click();
}

// Edit
document.addEventListener('click', e=>{
  const btn=e.target.closest('.js-edit'); if(!btn) return;
  e.preventDefault();
  if(typeof window.openEditModal==='function'){
    window.openEditModal({id:btn.dataset.id,title:btn.dataset.title||'',description:btn.dataset.description||''});
  }
});

// Toggle Public
document.addEventListener('click', async e=>{
  const btn=e.target.closest('.js-toggle-public'); if(!btn) return;
  e.preventDefault();
  const id=btn.dataset.id, cur=Number(btn.dataset.public)||0, nxt=cur?0:1;
  btn.disabled=true;
  const res=await fetch('toggle_visibility.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({id,is_public:nxt})});
  const d=await res.json();
  if(d?.ok){
    btn.dataset.public=String(nxt);
    const ic=btn.querySelector('i');
    if(ic){ic.className=nxt?'bi bi-eye-slash':'bi bi-eye';}
    const tr=btn.closest('tr');
    if(tr){const badge=tr.querySelector('.js-status-badge');if(badge){badge.textContent=nxt?'แสดงบนหน้าหลัก':'ซ่อนจากหน้าหลัก';badge.className=nxt?'badge bg-success js-status-badge':'badge bg-secondary js-status-badge';}}
  }
  btn.disabled=false;
});

// Delete
(()=>{
  const modalEl=document.getElementById('confirmDeleteModal');
  const titleEl=document.getElementById('cdm-title');
  const btnConfirm=document.getElementById('cdm-confirm');
  let deletePayload={id:null,csrf:null}; let targetRow=null;
  document.addEventListener('click', e=>{
    const btn=e.target.closest('.js-delete'); if(!btn) return; e.preventDefault();
    const tr=btn.closest('tr');
    deletePayload={id:btn.dataset.id,csrf:btn.dataset.csrf};
    targetRow=tr; titleEl.textContent=btn.dataset.title||'(ไม่มีชื่อ)';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
  btnConfirm.addEventListener('click', async()=>{
    if(!deletePayload.id) return; btnConfirm.disabled=true;
    const res=await fetch('delete_model.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({id:deletePayload.id,csrf:deletePayload.csrf})});
    const d=await res.json();
    if(d?.ok){ if(targetRow&&targetRow.parentNode) targetRow.parentNode.removeChild(targetRow); bootstrap.Modal.getInstance(modalEl)?.hide(); }
    else alert(d?.message||'ลบไม่สำเร็จ');
    btnConfirm.disabled=false; deletePayload={id:null,csrf:null}; targetRow=null;
  });
})();

// Unsave from saved tab
document.addEventListener('click', async e=>{
  const btn=e.target.closest('.js-unsave'); if(!btn) return; e.preventDefault();
  btn.disabled=true;
  const res=await fetch('collection.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'model_id='+btn.dataset.id});
  const d=await res.json();
  if(d?.saved===false){ btn.closest('tr')?.remove(); }
  btn.disabled=false;
});

// Three.js viewer (ใช้ใน view.php modal)
const modelModalEl=document.getElementById('modelModal');
const viewerWrap=document.querySelector('.viewer-wrap');
const viewerDom=document.getElementById('viewer');
let currentModel=null,pendingDataset=null;
const scene=new THREE.Scene(); scene.background=new THREE.Color(0x333333);
const camera=new THREE.PerspectiveCamera(40,1,0.01,10000);
const renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});
renderer.setPixelRatio(Math.min(devicePixelRatio,2));
viewerDom.appendChild(renderer.domElement);
scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dir=new THREE.DirectionalLight(0xffffff,1.3); dir.position.set(5,6,7); scene.add(dir);
const controls=new THREE.OrbitControls(camera,renderer.domElement); controls.enableDamping=true;
function resizeV(){const w=viewerWrap.clientWidth,h=viewerWrap.clientHeight||420;renderer.setSize(w,h,false);camera.aspect=w/h||1;camera.updateProjectionMatrix();}
new ResizeObserver(resizeV).observe(viewerWrap);
function fitCam(obj){const box=new THREE.Box3().setFromObject(obj);const sz=box.getSize(new THREE.Vector3()),c=box.getCenter(new THREE.Vector3());const ms=Math.max(sz.x,sz.y,sz.z)||1,fov=THREE.MathUtils.degToRad(camera.fov);const d=Math.max(ms/(2*Math.tan(fov/2)),ms/(2*Math.tan(fov/2)/camera.aspect))*1.35;camera.near=d/100;camera.far=d*100;camera.updateProjectionMatrix();camera.position.copy(c.clone().add(new THREE.Vector3(d,d,d)));controls.target.copy(c);controls.update();}
function clearM(){if(!currentModel)return;scene.remove(currentModel);currentModel.traverse(o=>{o.geometry?.dispose?.();(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m?.dispose?.());});currentModel=null;if(window.updateModelInfo)window.updateModelInfo(null);}
function loadM(file,ext){clearM();if(ext==='glb'||ext==='gltf'){const L=window.GLTFLoader?new GLTFLoader():new THREE.GLTFLoader();L.load(file,g=>{currentModel=g.scene||g.scenes?.[0]||g;scene.add(currentModel);fitCam(currentModel);if(window.updateModelInfo)window.updateModelInfo(currentModel);});}else if(ext==='obj'){const L=window.OBJLoader?new OBJLoader():new THREE.OBJLoader();L.load(file,obj=>{currentModel=obj;scene.add(obj);fitCam(obj);if(window.updateModelInfo)window.updateModelInfo(currentModel);});}}
modelModalEl?.addEventListener('shown.bs.modal',()=>{if(viewerWrap.clientHeight<10)viewerWrap.style.height='70vh';resizeV();if(pendingDataset){const ds=pendingDataset;pendingDataset=null;requestAnimationFrame(()=>loadM(ds.file,(ds.ext||'').toLowerCase()));}});
modelModalEl?.addEventListener('hidden.bs.modal',()=>{clearM();});
document.addEventListener('click',e=>{const a=e.target.closest('a.open-viewer');if(!a)return;pendingDataset=a.dataset;document.getElementById('viewerTitle').textContent=a.dataset.title||'';document.getElementById('viewerDesc').innerHTML=`<span>${a.dataset.desc||''}</span><div class="viewer-meta"><div>โดย: <strong>${a.dataset.uploader||'-'}</strong></div><div>${a.dataset.date||'-'}</div></div>`;document.getElementById('viewerDownload').href='download.php?id='+(a.dataset.id||'');});
(function animate(){requestAnimationFrame(animate);controls.update();renderer.render(scene,camera);})();
</script>
</body>
</html>

<?php
/* ============================================================
   OLD CODE (v1) — ก่อนเพิ่ม Tab บันทึกไว้ และ Tab สถิติ
   ไม่ได้ใช้งานแล้ว เก็บไว้เพื่อดูความเปลี่ยนแปลง
   ============================================================

   v1 มีเพียง Tab เดียวคือ "โมเดลของฉัน"
   แสดงรายการโมเดลในตาราง พร้อมปุ่ม แก้ไข / toggle public / ลบ

   SQL เดิม (v1):
   SELECT m.id, m.title, m.filename, m.description, m.created_at,
          EXISTS(...) AS is_collected,
          COALESCE(m.is_public, 1) AS is_public
   FROM models m
   WHERE m.user_id = :uid1
   ORDER BY m.created_at DESC

   สิ่งที่เพิ่มใน v2:
   - Query Tab 2: JOIN collections เพื่อดึงโมเดลที่บันทึกไว้
   - Query Tab 3: view_count, like_count, comment_count per model
   - Summary stats: totalViews, totalLikes, totalComments
   - Tab navigation JS
   - ปุ่ม js-unsave สำหรับลบออกจาก saved
   - แสดง License ในตาราง
   - ลิงก์ model.php?id=X แทน open-viewer modal
============================================================ */
?>
