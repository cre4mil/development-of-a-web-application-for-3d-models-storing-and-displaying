<?php
require 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['uid'])) {
  header('Location: login.php');
  exit;
}

$uid   = (int)($_SESSION['uid'] ?? 0);
$uname = htmlspecialchars($_SESSION['uname'] ?? 'User', ENT_QUOTES, 'UTF-8');

$myModels = [];
$total    = 0;

try {
  $sql = "
    SELECT 
      m.id, 
      m.title, 
      m.filename, 
      m.description, 
      m.created_at,
      -- เช็คว่าผู้ใช้จัดเก็บไว้หรือไม่
      EXISTS(SELECT 1 FROM collections c WHERE c.user_id = :uid2 AND c.model_id = m.id) AS is_collected,
      -- สมมติว่ามีคอลัมน์ is_public ใช้ตรวจว่าขึ้นหน้าเว็บไหม (ถ้าไม่มีให้ใส่ค่า 1 แทน)
      COALESCE(m.is_public, 1) AS is_public
    FROM models m
    WHERE m.user_id = :uid1
    ORDER BY m.created_at DESC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute(['uid1' => $uid, 'uid2' => $uid]);
  $myModels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $total    = count($myModels);
} catch (Throwable $e) {
  $myModels = [];
  $total    = 0;
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function human_filesize($bytes){
  if ($bytes <= 0) return '0 B';
  $units = ['B','KB','MB','GB','TB'];
  $p = floor(log($bytes, 1024));
  return number_format($bytes / pow(1024, $p), 2).' '.$units[$p];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>โปรไฟล์ – 3D Gallery</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <li class="nav-item dropdown">
          <a href="#" class="user-toggle dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar-circle"><?= mb_strtoupper(mb_substr($uname,0,1,'UTF-8')) ?></span>
            <span class="d-none d-sm-inline"><?= $uname ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header">
              <div class="name"><?= $uname ?></div>
              <div class="meta">เข้าสู่ระบบแล้ว</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="profile.php">โปรไฟล์</a></li>
            <li><a class="dropdown-item" href="like.php">โมเดลที่ชอบ</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php">ออกจากระบบ</a></li>
          </ul>
        </li>
      </ul>
    </nav>
  </div>
</header>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-1">โมเดลของฉัน</h2>
    <span class="text-muted">ทั้งหมด <strong><?= $total ?></strong> รายการ</span>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-light border text-center">ยังไม่มีโมเดลที่คุณอัปโหลด</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:80px">No.</th>
            <th>ชื่อโมเดล</th>
            <th class="text-center" style="width:200px">สถานะโมเดล</th>
            <th class="text-center" style="width:180px">การจัดการโมเดล</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($myModels as $m):
          $title = htmlspecialchars($m['title'] ?: basename($m['filename']));
          $desc  = htmlspecialchars($m['description'] ?? '');
          $file  = 'uploads/' . htmlspecialchars($m['filename']);
          $ext   = strtolower(pathinfo($m['filename'], PATHINFO_EXTENSION));
          $dateText = $m['created_at'] ? date('d/m/Y H:i', strtotime($m['created_at'])) : '-';
          $abs   = __DIR__ . '/uploads/' . $m['filename'];
          $sizeText = is_file($abs) ? human_filesize(@filesize($abs)) : '-';
          $collected = !empty($m['is_collected']);
        ?>
          <tr data-id="<?= (int)$m['id'] ?>">
            <td><?= $i++ ?></td>
            <td class="fw-semibold">
              <a href="#model"
                 class="open-viewer"
                 data-bs-toggle="modal" data-bs-target="#modelModal"
                 data-id="<?= (int)$m['id'] ?>"
                 data-title="<?= $title ?>"
                 data-desc="<?= $desc ?>"
                 data-file="<?= $file ?>"
                 data-ext="<?= $ext ?>"
                 data-uploader="<?= $uname ?>"
                 data-date="<?= $dateText ?>"
                 data-size="<?= $sizeText ?>">
                <?= $title ?>
              </a>
            </td>
            <td class="text-center">
              <?php if ((int)$m['is_public'] === 1): ?>
                <span class="badge bg-success">แสดงบนหน้าหลัก</span>
              <?php else: ?>
                <span class="badge bg-secondary">ซ่อนจากหน้าหลัก</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <button type="button"
                      class="btn btn-sm btn-outline-secondary me-1 js-edit"
                      title="แก้ไขโมเดล"
                      data-id="<?= (int)$m['id'] ?>"
                      data-title="<?= $title ?>"
                      data-description="<?= $desc ?>">
                <i class="bi bi-pencil-square"></i>
              </button>
              <a href="#" class="btn btn-sm btn-outline-success js-toggle-public"
                  data-id="<?= (int)$m['id'] ?>"
                  data-public="<?= (int)$m['is_public'] ?>">
                  <?= $m['is_public'] ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>' ?>
              </a>
              <button class="btn btn-sm btn-outline-danger js-delete"
                      title="ลบโมเดลนี้"
                      data-id="<?= (int)$m['id'] ?>"
                      data-title="<?= $title ?>"
                      data-csrf="<?= $csrf ?>">
                <i class="bi bi-trash3"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include 'view.php'; ?>

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
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.js-edit');
  if(!btn) return;
  e.preventDefault();
  if (typeof window.openEditModal === 'function') {
    window.openEditModal({
      id: btn.dataset.id,
      title: btn.dataset.title || '',
      description: btn.dataset.description || ''
    });
  }
});

function removeNodeWithFade(node){
  if (!node) return;
  node.classList.add('fade-out');
  setTimeout(()=>{ node.remove(); }, 200);
}

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
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: new URLSearchParams({ id, is_public: next }).toString()
    });
    const data = await res.json();

    if (!data || !data.ok) { console.error('toggle failed', data); return; }

    btn.dataset.public = String(next);
    btn.classList.toggle('is-on',  next === 1);
    btn.classList.toggle('is-off', next === 0);
    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye',       next === 1);
      icon.classList.toggle('bi-eye-slash', next === 0);
    }
    const tr = btn.closest('tr');
    if (tr) {
      const badge = tr.querySelector('.js-status-badge');
      if (badge) {
        if (next === 1) {
          badge.textContent = 'แสดงบนหน้าหลัก';
          badge.classList.add('badge-on');  badge.classList.remove('badge-off');
        } else {
          badge.textContent = 'ซ่อนจากหน้าหลัก';
          badge.classList.add('badge-off'); badge.classList.remove('badge-on');
        }
      }
    }

    if (next === 0) {
      const card = btn.closest('.card');
      if (card) {
        removeNodeWithFade(card);
      }
    }

  } catch (err) {
    console.error(err); 
  } finally {
    btn.disabled = false;
  }
});

(() => {
  const modalEl = document.getElementById('confirmDeleteModal');
  const titleEl = document.getElementById('cdm-title');
  const btnConfirm = document.getElementById('cdm-confirm');

  let targetRow = null;
  let deletePayload = { id: null, csrf: null, title: '' };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-delete');
    if (!btn) return;

    e.preventDefault();

    const tr = btn.closest('tr');
    deletePayload = {
      id: btn.dataset.id,
      csrf: btn.dataset.csrf,
      title: btn.dataset.title || ''
    };
    targetRow = tr;

    titleEl.textContent = deletePayload.title || '(ไม่มีชื่อ)';
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
  });

  btnConfirm.addEventListener('click', async () => {
    if (!deletePayload.id || !deletePayload.csrf) return;

    btnConfirm.disabled = true;

    try {
      const res = await fetch('delete_model.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams({
          id: deletePayload.id,
          csrf: deletePayload.csrf
        })
      });
      const data = await res.json();

      if (data?.ok) {
        if (targetRow && targetRow.parentNode) {
          targetRow.parentNode.removeChild(targetRow);
        }
        const badge = document.querySelector('.text-muted strong');
        if (badge) {
          const n = Math.max(0, (parseInt(badge.textContent, 10) || 1) - 1);
          badge.textContent = n;
        }
        bootstrap.Modal.getInstance(modalEl)?.hide();
      } else {
        alert(data?.message || 'ลบไม่สำเร็จ');
      }
    } catch (err) {
      console.error(err);
      alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
    } finally {
      btnConfirm.disabled = false;
      deletePayload = { id: null, csrf: null, title: '' };
      targetRow = null;
    }
  });
})();
</script>

<script>
const modelModalEl = document.getElementById('modelModal');
const viewerWrap   = document.querySelector('.viewer-wrap');
const viewerDom    = document.getElementById('viewer');

let currentModel=null, pendingDataset=null;

const scene   = new THREE.Scene(); scene.background = new THREE.Color(0x333333);
const camera  = new THREE.PerspectiveCamera(40, 1, 0.01, 10000);
const renderer= new THREE.WebGLRenderer({ antialias:true, alpha:true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
viewerDom.appendChild(renderer.domElement);

scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dir = new THREE.DirectionalLight(0xffffff,1.3); dir.position.set(5,6,7); scene.add(dir);
const controls = new THREE.OrbitControls(camera, renderer.domElement); controls.enableDamping = true;

function resizeViewer(){
  const w = viewerWrap.clientWidth, h = viewerWrap.clientHeight || 420;
  renderer.setSize(w,h,false); camera.aspect=w/h||1; camera.updateProjectionMatrix();
}
new ResizeObserver(resizeViewer).observe(viewerWrap);

function clearCurrent(){
  if(!currentModel) return;
  scene.remove(currentModel);
  currentModel.traverse(o=>{
    o.geometry?.dispose?.();
    (Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m?.dispose?.());
  });
  currentModel=null;
}
function fitCameraToObject(object){
  const box=new THREE.Box3().setFromObject(object);
  const size=box.getSize(new THREE.Vector3()), center=box.getCenter(new THREE.Vector3());
  const maxS=Math.max(size.x,size.y,size.z)||1, fov=THREE.MathUtils.degToRad(camera.fov);
  const distH=maxS/(2*Math.tan(fov/2)), distW=distH/camera.aspect, dist=Math.max(distH,distW)*1.35;
  camera.near=dist/100; camera.far=dist*100; camera.updateProjectionMatrix();
  camera.position.copy(center.clone().add(new THREE.Vector3(dist,dist,dist)));
  controls.target.copy(center); controls.update();
}
function updateModelInfo(root){
  const el=document.getElementById('modelInfo'); if(!root){el.textContent='';return;}
  let verts=0,tris=0,meshes=0,mats=new Set();
  root.traverse(o=>{
    if(o.isMesh){
      meshes++;
      const g=o.geometry, pos=g?.attributes?.position;
      if(pos){ verts+=pos.count; tris+=(g.index? g.index.count/3 : pos.count/3); }
      (Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m&&mats.add(m.name||m.type));
    }
  });
  const box=new THREE.Box3().setFromObject(root), s=box.getSize(new THREE.Vector3());
  const sc=root.scale, r=root.rotation, p=root.position;
  el.innerHTML =
    `<div class="mono">Dim: ${s.x.toFixed(3)} × ${s.y.toFixed(3)} × ${s.z.toFixed(3)}</div>
     <div class="mono">Scale: [${sc.x.toFixed(3)}, ${sc.y.toFixed(3)}, ${sc.z.toFixed(3)}]</div>
     <div class="mono">Pos: [${p.x.toFixed(2)}, ${p.y.toFixed(2)}, ${p.z.toFixed(2)}] RotY: ${THREE.MathUtils.radToDeg(r.y).toFixed(1)}°</div>
     <div class="mono">Meshes: ${meshes} • Materials: ${mats.size}</div>
     <div class="mono">Vertices: ${verts.toLocaleString()} • Triangles: ${Math.round(tris).toLocaleString()}</div>`;
}
function loadModel(file,ext){
  clearCurrent();
  if(ext==='glb' || ext==='gltf'){
    const L = (window.GLTFLoader? new GLTFLoader() : (THREE.GLTFLoader? new THREE.GLTFLoader():null));
    if(!L){ alert('ไม่พบ GLTFLoader'); return; }
    L.load(file,(gltf)=>{ currentModel=gltf.scene||gltf.scenes?.[0]||gltf; scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel); });
  }else if(ext==='obj'){
    const L = (window.OBJLoader? new OBJLoader() : (THREE.OBJLoader? new THREE.OBJLoader():null));
    if(!L){ alert('ไม่พบ OBJLoader'); return; }
    L.load(file,(obj)=>{ currentModel=obj; scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel); });
  }else{
    alert('ไม่รองรับไฟล์นี้');
  }
}

document.addEventListener('click', (e)=>{
  const a = e.target.closest('a.open-viewer');
  if(!a) return;
  pendingDataset = a.dataset;
  document.getElementById('viewerTitle').textContent = a.dataset.title || '';
  document.getElementById('viewerDesc').innerHTML =
    `<span class="viewer-desc-text">${a.dataset.desc || ''}</span>
     <div class="viewer-meta">
       <div>อัปโหลดโดย: <strong>${a.dataset.uploader || '-'}</strong></div>
       <div>วันที่อัปโหลด: ${a.dataset.date || '-'}</div>
       <div>ขนาดไฟล์: ${a.dataset.size || '-'}</div>
     </div>`;
  document.getElementById('viewerDownload').href = 'download.php?id='+(a.dataset.id||'');
});

modelModalEl.addEventListener('shown.bs.modal', ()=>{
  const wrapH = viewerWrap.clientHeight;
  if (wrapH < 10) viewerWrap.style.height = '70vh'; 
  resizeViewer();
  if (pendingDataset){
    const ds = pendingDataset; pendingDataset=null;
    requestAnimationFrame(()=> loadModel(ds.file, (ds.ext||'').toLowerCase()));
  }
});

(function animate(){ requestAnimationFrame(animate); controls.update(); renderer.render(scene,camera); })();
</script>
</body>
</html>
