<?php
require 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$search = trim($_GET['search'] ?? '');
$like   = "%$search%";

$perPage = 6;                       
$page    = max(1, (int)($_GET['page'] ?? 1)); 
$offset  = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*)
             FROM models m
             JOIN users u ON u.id = m.user_id
             WHERE m.title LIKE :s
               AND m.is_public = 1";

$cst = $pdo->prepare($countSql);
$cst->execute(['s' => $like]);
$totalRows  = (int)$cst->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

if ($offset >= $totalRows) {
  $page   = $totalPages;
  $offset = ($page - 1) * $perPage;
}

$dataSql = "SELECT m.id,m.title,m.thumb,m.user_id,m.filename,m.description,
                   m.created_at,m.is_public,u.username AS uploader
            FROM models m
            JOIN users u ON u.id = m.user_id
            WHERE m.title LIKE :s
              AND m.is_public = 1
            ORDER BY m.created_at DESC
            LIMIT :limit OFFSET :offset";

$st = $pdo->prepare($dataSql);
$st->bindValue(':s', $like, PDO::PARAM_STR);
$st->bindValue(':limit', $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset, PDO::PARAM_INT);
$st->execute();
$models = $st->fetchAll(PDO::FETCH_ASSOC);

$likedIds = $collectedIds = [];
if (isset($_SESSION['uid'])) {
  $st = $pdo->prepare("SELECT model_id FROM likes WHERE user_id=?");
  $st->execute([$_SESSION['uid']]);
  $likedIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'model_id')) ?: [];

  $st = $pdo->prepare("SELECT model_id FROM collections WHERE user_id=?");
  $st->execute([$_SESSION['uid']]);
  $collectedIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'model_id')) ?: [];
}

function human_filesize($bytes) {
  if ($bytes <= 0) return '0 B';
  $units = ['B','KB','MB','GB','TB'];
  $p = floor(log($bytes, 1024));
  return number_format($bytes / pow(1024, $p), 2).' '.$units[$p];
}

$uid = $_SESSION['uid'] ?? 0;
?>

<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <title>3D Model Gallery</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<header>
  <div class="container d-flex justify-content-between align-items-center">
    <a href="index.php" class="logo">3D Gallery</a>
    <nav>
      <ul>
        <?php if (isset($_SESSION['uid'])): ?>
          <li><a class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload</a></li>
          <li class="nav-item dropdown">
            <a href="#" class="user-toggle" data-bs-toggle="dropdown">
              <span class="avatar-circle"><?= htmlspecialchars(strtoupper(substr($_SESSION['uname'],0,1))) ?></span>
              <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['uname']) ?></span><span class="ms-1">▾</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="dropdown-header"><div class="name"><?= htmlspecialchars($_SESSION['uname']) ?></div><div class="meta">เข้าสู่ระบบแล้ว</div></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="profile.php">โปรไฟล์</a></li>
              <li><a class="dropdown-item" href="like.php">โมเดลที่ชอบ</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li><a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
          <li><a class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">Sign Up</a></li>
        <?php endif ?>
      </ul>
    </nav>
  </div>
</header>

<div class="container">
  <?php if (!empty($_SESSION['upload_error'])): ?>
    <div class="alert alert-danger mt-3">
      <?= htmlspecialchars($_SESSION['upload_error']) ?>
    </div>
    <?php unset($_SESSION['upload_error']); ?>
  <?php endif; ?>
  <?php if (isset($_GET['uploads']) && $_GET['uploads']=='1'): ?>
    <div class="alert alert-success mt-3">อัปโหลดสำเร็จแล้ว!</div>
  <?php endif; ?>

  <div class="search-bar">
    <form method="get" action="index.php" class="d-flex gap-2">
      <input type="search" name="search" class="form-control" placeholder="ค้นหาโมเดล" value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-warning px-4">ค้นหา</button>
    </form>
  </div>

  <div class="gallery">
    <?php foreach ($models as $m):
      $ext  = strtolower(pathinfo($m['filename'], PATHINFO_EXTENSION));
      $file = 'uploads/'.htmlspecialchars($m['filename']);
      $desc = htmlspecialchars($m['description'] ?? '');
      $isLiked     = in_array((int)$m['id'], (array)$likedIds, true);
      $isCollected = isset($_SESSION['uid']) && in_array((int)$m['id'], (array)$collectedIds, true);
      $absPath  = __DIR__ . '/uploads/' . $m['filename'];     
      $bytes    = is_file($absPath) ? @filesize($absPath) : 0;
      $sizeText = human_filesize($bytes);
      $uploader = htmlspecialchars($m['uploader'] ?? '-');
      $dateText = $m['created_at'] ? date('d/m/Y H:i', strtotime($m['created_at'])) : '-';
    ?>

    <div class="card">
      <button class="btn btn-light btn-sm shadow-sm like-toggle <?= $isLiked?'liked':'' ?>" data-id="<?= (int)$m['id'] ?>" title="ชอบ">
        <?= $isLiked ? '❤' : '♡' ?>
      </button>

      <?php if ($uid): ?>
        <div class="dropdown card-actions" data-card-id="<?= (int)$m['id'] ?>">
          <button class="btn btn-light btn-sm shadow-sm action-toggle" data-bs-toggle="dropdown">☰</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a href="#" class="dropdown-item open-viewer"
                 data-id="<?= (int)$m['id'] ?>"
                 data-title="<?= htmlspecialchars($m['title']) ?>"
                 data-desc="<?= $desc ?>"
                 data-file="<?= $file ?>"
                 data-ext="<?= $ext ?>"
                 data-uploader="<?= $uploader ?>"
                 data-date="<?= $dateText ?>"
                 data-size="<?= $sizeText ?>">โชว์โมเดล</a>
            </li>

            <?php if ((int)$uid === (int)$m['user_id']): ?>
              <li><a href="#" class="dropdown-item open-edit" data-id="<?= (int)$m['id'] ?>">แก้ไข</a></li>
              <li>
                <a href="#" class="dropdown-item js-toggle-public"
                data-id="<?= (int)$m['id'] ?>"
                data-public="<?= (int)$m['is_public'] ?>">
                <?= $m['is_public'] ? 'ไม่แสดงบนหน้าหลัก' : 'แสดงบนหน้าหลัก' ?>
              </a>
              </li>
              <li>
                <a href="#" class="dropdown-item text-danger delete-model"
                   data-id="<?= (int)$m['id'] ?>"
                   data-title="<?= htmlspecialchars($m['title']) ?>">ลบ</a>
              </li>
            <?php endif; ?>

            <li><a class="dropdown-item" href="download.php?id=<?= (int)$m['id'] ?>">ดาวน์โหลด</a></li>
          </ul>
        </div>
      <?php endif; ?>

      <img src="uploads/<?= htmlspecialchars($m['thumb']) ?>"
           alt="<?= htmlspecialchars($m['title']) ?>"
           class="open-viewer"
           data-id="<?= (int)$m['id'] ?>"
           data-title="<?= htmlspecialchars($m['title']) ?>"
           data-desc="<?= $desc ?>"
           data-file="<?= $file ?>"
           data-ext="<?= $ext ?>"
           data-uploader="<?= $uploader ?>"
           data-date="<?= $dateText ?>"
           data-size="<?= $sizeText ?>" />

      <div class="card-body text-center">
        <h5 class="mb-1">
          <a href="#" class="open-viewer"
             data-id="<?= (int)$m['id'] ?>"
             data-title="<?= htmlspecialchars($m['title']) ?>"
             data-desc="<?= $desc ?>"
             data-file="<?= $file ?>"
             data-ext="<?= $ext ?>"
             data-uploader="<?= $uploader ?>"
             data-date="<?= $dateText ?>"
             data-size="<?= $sizeText ?>"><?= htmlspecialchars($m['title']) ?></a>
        </h5>
      </div>
    </div>
    <?php endforeach; ?>

<?php
function build_qs(array $extra = []) {
  $q = $_GET; unset($q['page']); $q = array_merge($q, $extra);
  return http_build_query($q);
}
?>
  <?php if (empty($models)): ?>
    <p class="no-models">ยังไม่มีโมเดลในระบบ</p>
  <?php endif; ?>
</div> 
</div> 

<?php include 'view.php'; ?>  

<div class="pagination-section text-center my-4">
  <nav aria-label="Models pagination">
    <ul class="pagination justify-content-center" id="pagination-nav">
      <?php
        function build_query($extra = []) {$params = $_GET;unset($params['page']);$params = array_merge($params, $extra);return 'index.php?' . http_build_query($params);}
        $prev = max(1, $page - 1);
        $next = min($totalPages, $page + 1);
      ?>
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <button class="page-link" data-target="<?= build_query(['page' => 1]) ?>">&laquo;</button>
      </li>
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <button class="page-link" data-target="<?= build_query(['page' => $prev]) ?>">&lsaquo;</button>
      </li>
      <li class="page-item disabled">
        <span class="page-link">หน้า <?= $page ?> / <?= $totalPages ?></span>
      </li>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <button class="page-link" data-target="<?= build_query(['page' => $next]) ?>">&rsaquo;</button>
      </li>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <button class="page-link" data-target="<?= build_query(['page' => $totalPages]) ?>">&raquo;</button>
      </li>
    </ul>
  </nav>
</div>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-left">
      <h4>Anurak Kalapun</h4>
      <p>Thailand : +66985292682</p>
      <p>Email : <a href="mailto:65160131@go.buu.ac.th.com">65160131@go.buu.ac.th.com</a></p>
    </div>
    <div class="footer-right">
      <h4>© 2025 – ITDI-Informatics-Burapha University</h4>
    </div>
  </div>
</footer>

<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ยืนยันการลบโมเดล</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        ต้องการลบโมเดล "<span id="cdm-title"></span>" หรือไม่?<br>
        <small class="text-muted">ไฟล์และข้อมูลทั้งหมดจะถูกลบถาวร</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="cdm-confirm">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/GLTFLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/loaders/OBJLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.130.0/examples/js/controls/OrbitControls.js"></script>

<script>
const modelModalEl = document.getElementById('modelModal');
const modelModal   = new bootstrap.Modal(modelModalEl);
const container    = document.getElementById('viewer');

const scene = new THREE.Scene(); scene.background = new THREE.Color(0x333333);
const camera = new THREE.PerspectiveCamera(40, 1, 0.01, 10000);
const renderer = new THREE.WebGLRenderer({antialias:true,alpha:true});
renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
container.appendChild(renderer.domElement);

scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dir=new THREE.DirectionalLight(0xffffff,1.3); dir.position.set(5,6,7); scene.add(dir);
const controls = new THREE.OrbitControls(camera, renderer.domElement); controls.enableDamping = true;

let currentModel=null, pendingDataset=null;

function resizeViewer(){
  const wrap=document.querySelector('.viewer-wrap');
  const w=wrap.clientWidth, h=wrap.clientHeight||400;
  renderer.setSize(w,h,false); camera.aspect=w/h||1; camera.updateProjectionMatrix();
}
new ResizeObserver(resizeViewer).observe(document.querySelector('.viewer-wrap'));

modelModalEl.addEventListener('shown.bs.modal', ()=>{
  resizeViewer();
  if(pendingDataset){
    const ds=pendingDataset; pendingDataset=null;
    requestAnimationFrame(()=>loadModel(ds.file,(ds.ext||'').toLowerCase()));
  }
});

function fitCameraToObject(object){
  const box = new THREE.Box3().setFromObject(object);
  const size=box.getSize(new THREE.Vector3()); const center=box.getCenter(new THREE.Vector3());
  const maxS=Math.max(size.x,size.y,size.z)||1;
  const fov=THREE.MathUtils.degToRad(camera.fov);
  const distH=maxS/(2*Math.tan(fov/2)), distW=distH/camera.aspect, dist=Math.max(distH,distW)*1.35;
  camera.near=dist/100; camera.far=dist*100; camera.updateProjectionMatrix();
  camera.position.copy(center.clone().add(new THREE.Vector3(dist,dist,dist)));
  controls.target.copy(center); controls.update();
}

function clearCurrent(){
  if(!currentModel) return;
  scene.remove(currentModel);
  currentModel.traverse(o=>{
    o.geometry?.dispose?.();
    if(o.material){ (Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m.dispose?.()); }
  });
  currentModel=null;
}
function normalizeURL(u){ try{return encodeURI(u);}catch(_){return u;} }

function updateModelInfo(root, file=null){
  const infoEl=document.getElementById('modelInfo'); if(!root){infoEl.textContent='';return;}
  let verts=0,tris=0,meshes=0,mats=new Set();
  root.traverse(o=>{
    if(o.isMesh){
      meshes++;
      const g=o.geometry, pos=g?.attributes?.position;
      if(pos){ verts+=pos.count; tris+=(g.index?g.index.count/3:pos.count/3); }
      const arr=Array.isArray(o.material)?o.material:[o.material]; arr.forEach(m=>m && mats.add(m.name||m.type));
    }
  });
  const box=new THREE.Box3().setFromObject(root); const s=box.getSize(new THREE.Vector3());
  const sc=root.scale, r=root.rotation, p=root.position;
  const fileLine=file?`${file} `:'file';
  infoEl.innerHTML = `
    <div class="mono">Dim: ${s.x.toFixed(3)} × ${s.y.toFixed(3)} × ${s.z.toFixed(3)}</div>
    <div class="mono">Scale: [${sc.x.toFixed(3)}, ${sc.y.toFixed(3)}, ${sc.z.toFixed(3)}]</div>
    <div class="mono">Pos: [${p.x.toFixed(2)}, ${p.y.toFixed(2)}, ${p.z.toFixed(2)}]  RotY: ${THREE.MathUtils.radToDeg(r.y).toFixed(1)}°</div>
    <div class="mono">Meshes: ${meshes} • Materials: ${mats.size}</div>
    <div class="mono">Vertices: ${verts.toLocaleString()} • Triangles: ${Math.round(tris).toLocaleString()}</div>`;
}

function loadModel(file,ext){
  clearCurrent();
  const url=normalizeURL(file);
  if(ext==='glb'||ext==='gltf'){
    const L = (window.GLTFLoader? new GLTFLoader() : (THREE.GLTFLoader? new THREE.GLTFLoader():null));
    if(!L){ alert('ไม่พบ GLTFLoader'); return; }
    L.load(url,(gltf)=>{
      currentModel=gltf.scene||gltf.scenes?.[0]||gltf;
      scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel,file);
    }, undefined, ()=>alert('โหลด glb/gltf ล้มเหลว'));
  }else if(ext==='obj'){
    const L = (window.OBJLoader? new OBJLoader() : (THREE.OBJLoader? new THREE.OBJLoader():null));
    if(!L){ alert('ไม่พบ OBJLoader'); return; }
    L.load(url,(obj)=>{
      currentModel=obj; scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel,file);
    }, undefined, ()=>alert('โหลด obj ล้มเหลว'));
  }else{
    alert('ไม่รองรับไฟล์นี้');
  }
}

function openViewer(ds){
  document.getElementById('viewerTitle').textContent = ds.title || '';

  const descEl = document.getElementById('viewerDesc');
  const safeDesc = ds.desc || '';
  descEl.innerHTML = `
    <span class="viewer-desc-text">${safeDesc}</span>
    <div class="viewer-meta">
      <div>อัปโหลดโดย: <strong>${ds.uploader || '-'}</strong></div>
      <div>วันที่อัปโหลด: ${ds.date || '-'}</div>
      <div>ขนาดไฟล์: ${ds.size || '-'}</div>
    </div>
  `;
  document.getElementById('viewerDownload').href = 'download.php?id=' + (ds.id || '');
  pendingDataset = ds;
  modelModal.show();
}


document.addEventListener('click', e=>{
  const t=e.target.closest('.open-viewer'); if(!t) return;
  e.preventDefault();
  openViewer(t.dataset);
});

document.addEventListener('click', async e=>{
  const btn=e.target.closest('.like-toggle'); if(!btn) return; e.preventDefault();
  const id=btn.dataset.id; btn.disabled=true;
  try{
    const res=await fetch('like.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:new URLSearchParams({id})});
    const data=await res.json();
    if(data?.liked){ btn.classList.add('liked'); btn.textContent='❤'; } else { btn.classList.remove('liked'); btn.textContent='♡'; }
  }catch(_){ alert('ผิดพลาด'); } finally{ btn.disabled=false; }
});

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-toggle-public');
  if (!btn) return;

  e.preventDefault();

  const id      = btn.dataset.id;
  const current = Number(btn.dataset.public) || 0; 
  const next    = current ? 0 : 1;
  btn.disabled = true;

  try {
    const res = await fetch('toggle_visibility.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
      body: new URLSearchParams({ id, is_public: next }).toString()
    });
    const data = await res.json();

    if (!data || !data.ok) {
      console.error('toggle failed', data);
      btn.disabled = false;
      return;
    }

    btn.dataset.public = String(next);

    btn.classList.toggle('is-on',  next === 1);
    btn.classList.toggle('is-off', next === 0);

    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye',        next === 1);
      icon.classList.toggle('bi-eye-slash',  next === 0);
    }

    const tr = btn.closest('tr');
    if (tr) {
      const badge = tr.querySelector('.js-status-badge');
      if (badge) {
        if (next === 1) {
          badge.textContent = 'แสดงบนหน้าหลัก';
          badge.classList.remove('badge-off');
          badge.classList.add('badge-on');
        } else {
          badge.textContent = 'ซ่อนจากหน้าหลัก';
          badge.classList.remove('badge-on');
          badge.classList.add('badge-off');
        }
      }
    }

  } catch (err) {
    console.error(err); 
  } finally {
    btn.disabled = false;
  }
});

const editModal = new bootstrap.Modal(document.getElementById('editModal'));
document.addEventListener('click', async e=>{
  const a=e.target.closest('.open-edit'); if(!a) return; e.preventDefault();
  const id=a.dataset.id;
  const res=await fetch('edit.php?action=get&id='+encodeURIComponent(id));
  if(!res.ok){ alert('โหลดไม่สำเร็จ'); return; }
  const data=await res.json();
  document.getElementById('edit_id').value=data.id;
  document.getElementById('edit_title').value=data.title||'';
  document.getElementById('edit_description').value=data.description||'';
  editModal.show();
});

document.getElementById('editForm').addEventListener('submit', async e=>{e.preventDefault();
  const fd=new FormData(e.target); fd.append('action','update');
  const res=await fetch('edit.php', {method:'POST', body:fd});
  const data=await res.json();
  if(data?.ok){ editModal.hide(); location.reload(); } else alert('บันทึกไม่สำเร็จ');
});

(() => {
  const modalEl = document.getElementById('confirmDeleteModal');
  if (!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);
  const titleEl = document.getElementById('cdm-title');
  const confirmBtn = document.getElementById('cdm-confirm');

  let delCtx = null; 

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.delete-model');
    if (!btn) return;
    e.preventDefault();

    delCtx = {
      id: btn.dataset.id,
      title: btn.dataset.title || '',
      anchor: btn
    };
    titleEl.textContent = delCtx.title;
    modal.show();
  });

  confirmBtn.addEventListener('click', async () => {
    if (!delCtx) return;
    const fd = new URLSearchParams({action: 'delete',id: delCtx.id,csrf: '<?= htmlspecialchars($_SESSION["csrf"] ?? "", ENT_QUOTES, "UTF-8") ?>'});
    
    try {
      const res = await fetch('edit.php', { method:'POST', body: fd });
      const data = await res.json();
      if (data?.ok) {
        const row = delCtx.anchor.closest('tr');   
        const card = delCtx.anchor.closest('.card'); 
        if (row) row.remove();
        if (card) card.remove();
        modal.hide();
      } else {
        alert(data?.message || 'ลบไม่สำเร็จ');
      }
    } catch (err) {
      alert('เครือข่ายผิดพลาด');
    } finally {
      delCtx = null;
    }
  });
})();

document.addEventListener('DOMContentLoaded', () => {
  const p = new URLSearchParams(location.search);
  if (p.get('success') === '1') {
    const m = new bootstrap.Modal(document.getElementById('loginModal'));
    m.show();
    const body = document.querySelector('#loginModal .modal-body');
    if (body && !body.querySelector('.alert-success')) {
      body.insertAdjacentHTML('afterbegin',
        '<div class="alert alert-success mb-3 text-center">✅ สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</div>');
    }
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const pageLinks = document.querySelectorAll('#pagination-nav .page-link');
  pageLinks.forEach(btn => {
    btn.addEventListener('click', e => {
      const url = e.currentTarget.dataset.target;
      if (url && !e.currentTarget.closest('.disabled')) {
        window.location.href = url; 
      }
    });
  });
});

(function animate(){ requestAnimationFrame(animate); controls.update(); renderer.render(scene,camera); })();
</script>
</body>
</html>
