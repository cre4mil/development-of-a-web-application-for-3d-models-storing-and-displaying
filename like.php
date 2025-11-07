<?php
require 'connect.php';

if (!isset($_SESSION['uid'])) {
  header('Location: index.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
  header('Content-Type: application/json; charset=utf-8');
  $mid = (int)($_POST['id'] ?? 0);
  if ($mid <= 0) { http_response_code(400); echo json_encode(['error'=>'bad_id']); exit; }

  $chk = $pdo->prepare("SELECT 1 FROM likes WHERE user_id=? AND model_id=?");
  $chk->execute([$_SESSION['uid'], $mid]);
  if ($chk->fetch()) {
    $pdo->prepare("DELETE FROM likes WHERE user_id=? AND model_id=?")->execute([$_SESSION['uid'], $mid]);
    echo json_encode(['liked'=>false]);
  } else {
    $pdo->prepare("INSERT INTO likes (user_id, model_id) VALUES (?, ?)")->execute([$_SESSION['uid'], $mid]);
    echo json_encode(['liked'=>true]);
  }
  exit;
}

function human_filesize($bytes){
  if ($bytes <= 0) return '0 B';
  $units = ['B','KB','MB','GB','TB'];
  $p = floor(log($bytes, 1024));
  return number_format($bytes / pow(1024, $p), 2).' '.$units[$p];
}

$st = $pdo->prepare("
  SELECT m.id, m.title, m.thumb, m.user_id, m.filename, m.description, m.created_at, u.username AS uploader
  FROM models m
  JOIN likes l ON l.model_id = m.id
  JOIN users u ON u.id = m.user_id
  WHERE l.user_id = ?
  ORDER BY m.created_at DESC
");
$st->execute([$_SESSION['uid']]);
$models = $st->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>โมเดลที่ชอบ – 3D Gallery</title>
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
          <a href="#" class="user-toggle" data-bs-toggle="dropdown">
            <span class="avatar-circle"><?= htmlspecialchars(strtoupper(substr($_SESSION['uname'],0,1))) ?></span>
            <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['uname']) ?></span>
            <span class="ms-1">▾</span>
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
      </ul>
    </nav>
  </div>
</header>

<div class="container py-4 text-center">
  <h2 class="mb-1">โมเดลที่ชอบ</h2>
  <div class="text-muted mb-3">ทั้งหมด <?= count($models) ?> รายการ</div>


  <div class="gallery">
    <?php foreach ($models as $m):
      $ext   = strtolower(pathinfo($m['filename'], PATHINFO_EXTENSION));
      $file  = 'uploads/'.htmlspecialchars($m['filename']);
      $desc  = htmlspecialchars($m['description'] ?? '');
      $absPath  = __DIR__ . '/uploads/' . $m['filename'];   
      $bytes    = is_file($absPath) ? @filesize($absPath) : 0;
      $sizeText = human_filesize($bytes);
      $uploader = htmlspecialchars($m['uploader'] ?? '-');
      $dateText = $m['created_at'] ? date('d/m/Y H:i', strtotime($m['created_at'])) : '-';
    ?>
      <div class="card" data-card-id="<?= (int)$m['id'] ?>">
        <button class="btn btn-light btn-sm shadow-sm like-toggle liked"
                data-id="<?= (int)$m['id'] ?>" title="เลิกถูกใจ">❤</button>

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

    <?php if (empty($models)): ?>
    <p class="text-muted">ยังไม่มีรายการที่ชอบ</p>
  <?php endif; ?>
  </div>
</div>

<?php include 'view.php'; ?>

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
const renderer = new THREE.WebGLRenderer({ antialias:true, alpha:true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
container.appendChild(renderer.domElement);

scene.add(new THREE.AmbientLight(0xffffff,1.1));
const dir = new THREE.DirectionalLight(0xffffff,1.3); dir.position.set(5,6,7); scene.add(dir);

const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

let currentModel=null, pendingDataset=null;


function resizeViewer(){
  const wrap=document.querySelector('.viewer-wrap');
  const w=wrap.clientWidth, h=wrap.clientHeight||400;
  renderer.setSize(w,h);                               
  renderer.domElement.style.width  = w + 'px';
  renderer.domElement.style.height = h + 'px';
  camera.aspect=w/h||1; camera.updateProjectionMatrix();
  if (currentModel) fitCameraToObject(currentModel);
}
new ResizeObserver(resizeViewer).observe(document.querySelector('.viewer-wrap'));
modelModalEl.addEventListener('shown.bs.modal', ()=>{
  resizeViewer();
  if(pendingDataset){
    const ds=pendingDataset; pendingDataset=null;
    requestAnimationFrame(()=>loadModel(ds.file,(ds.ext||'').toLowerCase()));
  }else if(currentModel){
    fitCameraToObject(currentModel);
  }
});

function fitCameraToObject(object){
  const box=new THREE.Box3().setFromObject(object);
  const size=box.getSize(new THREE.Vector3()), center=box.getCenter(new THREE.Vector3());
  const maxS=Math.max(size.x,size.y,size.z)||1;
  const fov=THREE.MathUtils.degToRad(camera.fov);
  const distH=maxS/(2*Math.tan(fov/2)), distW=distH/camera.aspect, dist=Math.max(distH,distW)*1.35;
  camera.near=dist/100; camera.far=dist*100; camera.updateProjectionMatrix();
  camera.position.copy(center.clone().add(new THREE.Vector3(dist,dist,dist)));
  controls.target.copy(center); controls.update();
}
function clearCurrent(){ if(!currentModel) return; scene.remove(currentModel); currentModel.traverse(o=>{o.geometry?.dispose?.(); if(o.material){(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>m.dispose?.());}}); currentModel=null; }

function updateModelInfo(root){
  const infoEl=document.getElementById('modelInfo'); if(!root){infoEl.textContent='';return;}
  let verts=0,tris=0,meshes=0,mats=new Set();
  root.traverse(o=>{ if(o.isMesh){ meshes++; const g=o.geometry,pos=g?.attributes?.position; if(pos){ verts+=pos.count; tris+=(g.index?g.index.count/3:pos.count/3);} const arr=Array.isArray(o.material)?o.material:[o.material]; arr.forEach(m=>m&&mats.add(m.name||m.type)); }});
  const box=new THREE.Box3().setFromObject(root); const s=box.getSize(new THREE.Vector3());
  const sc=root.scale, r=root.rotation, p=root.position;
  infoEl.innerHTML =
  `<div class="mono">Dim: ${s.x.toFixed(3)} × ${s.y.toFixed(3)} × ${s.z.toFixed(3)}</div>
   <div class="mono">Scale: [${sc.x.toFixed(3)}, ${sc.y.toFixed(3)}, ${sc.z.toFixed(3)}]</div>
   <div class="mono">Pos: [${p.x.toFixed(2)}, ${p.y.toFixed(2)}, ${p.z.toFixed(2)}]  RotY: ${THREE.MathUtils.radToDeg(r.y).toFixed(1)}°</div>
   <div class="mono">Meshes: ${meshes} • Materials: ${mats.size}</div>
   <div class="mono">Vertices: ${verts.toLocaleString()} • Triangles: ${Math.round(tris).toLocaleString()}</div>`;
}

function loadModel(file,ext){
  clearCurrent();
  if(ext==='glb'||ext==='gltf'){
    const L = (window.GLTFLoader? new GLTFLoader() : (THREE.GLTFLoader? new THREE.GLTFLoader():null));
    if(!L){alert('ไม่พบ GLTFLoader');return;}
    L.load(file,(gltf)=>{ currentModel=gltf.scene||gltf.scenes?.[0]||gltf; scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel); });
  }else if(ext==='obj'){
    const L = (window.OBJLoader? new OBJLoader() : (THREE.OBJLoader? new THREE.OBJLoader():null));
    if(!L){alert('ไม่พบ OBJLoader');return;}
    L.load(file,(obj)=>{ currentModel=obj; scene.add(currentModel); fitCameraToObject(currentModel); updateModelInfo(currentModel); });
  }else alert('ไม่รองรับไฟล์นี้');
}

function openViewer(ds){
  document.getElementById('viewerTitle').textContent = ds.title||'';

  const descEl = document.getElementById('viewerDesc');
  const safeDesc = ds.desc || '';
  descEl.innerHTML = `
    <span class="viewer-desc-text">${safeDesc}</span>
    <div class="viewer-meta">
      <div>อัปโหลดโดย: <strong>${ds.uploader || '-'}</strong></div>
      <div>วันที่อัปโหลด: ${ds.date || '-'}</div>
      <div>ขนาดไฟล์: ${ds.size || '-'}</div>
    </div>`;

  document.getElementById('viewerDownload').href = 'download.php?id='+(ds.id||'');
  pendingDataset=ds; modelModal.show();
}

document.addEventListener('click', e=>{
  const t=e.target.closest('.open-viewer'); if(!t) return;
  e.preventDefault();
  openViewer(t.dataset);
});

document.addEventListener('click', async e=>{
  const btn=e.target.closest('.like-toggle'); if(!btn) return;
  e.preventDefault(); const id=btn.dataset.id; btn.disabled=true;
  try{
    const res=await fetch('like.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({id})});
    const data=await res.json();
    if(data?.liked===false){ btn.closest('.card')?.remove(); }
    else if(data?.liked===true){ btn.textContent='❤'; btn.classList.add('liked'); }
  }catch(err){ console.error(err); alert('เกิดข้อผิดพลาด'); } finally{ btn.disabled=false; }
});

(function animate(){ requestAnimationFrame(animate); controls.update(); renderer.render(scene,camera); })();
</script>
</body>
</html>
