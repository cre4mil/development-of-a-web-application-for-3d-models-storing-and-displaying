<?php
if (!defined('VIEW_INCLUDED')) {
    define('VIEW_INCLUDED', true);
}
?>

<?php
$licenses = ['CC BY','CC BY-SA','CC BY-ND','CC BY-NC','CC BY-NC-SA','CC BY-NC-ND','CC0','All Rights Reserved'];
?>

<!-- ===== Login Modal ===== -->
<div class="modal fade login-modal" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <?php if (!empty($_SESSION['login_error'])): ?>
          <div class="alert alert-danger mb-3"><?= htmlspecialchars($_SESSION['login_error']) ?></div>
        <?php endif; ?>
          <form method="post" action="login.php" class="login-form">
          <div class="mb-3">
            <label class="form-label" for="login_email">อีเมล</label>
            <input type="email" id="login_email" name="email" class="form-control" required autocomplete="email">
          </div>
          <div class="mb-3">
            <label class="form-label" for="login_password">รหัสผ่าน</label>
            <input type="password" id="login_password" name="password" class="form-control" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary w-100">เข้าสู่ระบบ</button>
          <a class="btn btn-link d-block text-center mt-2" data-bs-target="#registerModal" data-bs-toggle="modal" data-bs-dismiss="modal">ยังไม่มีบัญชี? สมัครสมาชิก</a>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Sign Up Modal -->
<div class="modal fade register-modal" id="registerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Sign Up</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-0">
        <form method="post" action="register.php" class="register-form" autocomplete="off">
          <div class="mb-3">
            <label class="form-label" for="reg_username">ชื่อผู้ใช้</label>
            <input type="text" id="reg_username" name="username" class="form-control rounded-3" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reg_email">อีเมล</label>
            <input type="email" id="reg_email" name="email" class="form-control rounded-3" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reg_password">รหัสผ่าน</label>
            <input type="password" id="reg_password" name="password" class="form-control rounded-3" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="reg_confirm_password">ยืนยันรหัสผ่าน</label>
            <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control rounded-3" required>
          </div>
          <button type="submit" class="btn signup-btn">สมัครสมาชิก</button>
          <div class="text-center mt-3">
            <a class="btn btn-link d-block text-center mt-2" data-bs-target="#loginModal" data-bs-toggle="modal" data-bs-dismiss="modal">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===== Upload Modal ===== -->
<div class="modal fade upload-modal" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload a new model</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <form id="uploadForm" method="post" action="uploads.php" enctype="multipart/form-data" class="upload-form">
          <div class="mb-3">
            <label class="form-label" for="upload_title">ชื่อโมเดล</label>
            <input id="upload_title" name="title" class="form-control" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="upload_model">ไฟล์โมเดล (.obj/.glb/.gltf)</label>
              <input type="file" id="upload_model" name="model" class="form-control" accept=".obj,.glb,.gltf" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="upload_thumb">Thumbnail (jpg/png)</label>
              <input type="file" id="upload_thumb" name="thumb" class="form-control" accept=".jpg,.jpeg,.png" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="upload_description">คำอธิบาย</label>
            <textarea id="upload_description" name="description" rows="2" class="form-control"></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-success fw-bold" for="upload_price"><i class="bi bi-tag-fill"></i> ตั้งราคา (บาท)</label>
              <input type="number" step="0.01" min="0" id="upload_price" name="price" class="form-control border-success" placeholder="0.00 (ฟรี)">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="upload_license">License</label>
              <select id="upload_license" name="license" class="form-select">
                <?php foreach($licenses as $l): ?>
                <option value="<?= $l ?>"><?= $l ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="upload_tags">Tags <small class="text-muted">(คั่นด้วยจุลภาค)</small></label>
              <input id="upload_tags" name="tags" class="form-control" placeholder="เช่น vehicle, sci-fi">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer justify-content-end">
        <button form="uploadForm" type="submit" class="btn btn-primary">อัปโหลด</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Viewer Modal ===== -->
<div class="modal fade model-modal" id="modelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="border:none; border-radius:12px; overflow:hidden;">
      <div class="modal-body p-0 position-relative">
        <button type="button" class="btn-close position-absolute" style="top:15px; right:15px; z-index:100; filter: invert(1); opacity: 0.8;" data-bs-dismiss="modal"></button>
        <div class="viewer-wrap" id="modalViewerWrap" style="height: 60vh; border-bottom: 1px solid #ddd;">
          <div class="viewer-toolbar">
            <button class="viewer-toolbar-btn" title="Help"><i class="bi bi-question-lg"></i></button>
            <button class="viewer-toolbar-btn position-relative" title="Settings">
              <i class="bi bi-gear"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info" style="font-size: 0.5rem;">HD</span>
            </button>
            <button class="viewer-toolbar-btn" id="btnToggleInspectorModal" title="Model Inspector"><i class="bi bi-layers"></i></button>
            <button class="viewer-toolbar-btn" title="VR"><i class="bi bi-badge-vr"></i></button>
            <button class="viewer-toolbar-btn" id="btnFullscreenModal" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
          </div>
          <div class="model-inspector" id="modelInspectorModal">
            <div class="inspector-header">
              <div><i class="bi bi-box"></i> Model Inspector</div>
              <button class="btn btn-sm text-white p-0" id="btnCloseInspectorModal"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="inspector-section">
            <div class="inspector-section-title">Wireframe Color</div>
            <div class="d-flex gap-2">
              <button class="color-swatch checker-swatch active modal-swatch" data-color="default"></button>
              <button class="color-swatch modal-swatch" data-color="#000000" style="background-color: #000000; border-color: rgba(255,255,255,0.2)"></button>
              <button class="color-swatch modal-swatch" data-color="#cccccc" style="background-color: #cccccc;"></button>
              <button class="color-swatch modal-swatch" data-color="#ff0000" style="background-color: #ff0000;"></button>
              <button class="color-swatch modal-swatch" data-color="#0000ff" style="background-color: #0000ff;"></button>
              <button class="color-swatch modal-swatch" data-color="#00ff00" style="background-color: #00ff00;"></button>
              <button class="color-swatch modal-swatch" data-color="#ffff00" style="background-color: #ffff00;"></button>
            </div>
          </div>
          <div class="inspector-section">
            <label class="form-check-label d-flex align-items-center gap-2" style="cursor:pointer;" for="modalSingleSidedToggle">
              <div class="form-check form-switch m-0 p-0 fs-5 d-flex align-items-center">
                <input class="form-check-input m-0" type="checkbox" id="modalSingleSidedToggle">
              </div>
              <span class="fs-6 fw-bold">Single Sided</span>
            </label>
          </div>
            <div class="inspector-section">
              <div class="inspector-section-title">Render (2)</div>
              <button class="inspector-btn active modal-inspector-btn" data-mode="final"><i class="bi bi-palette"></i> Final Render</button>
              <button class="inspector-btn modal-inspector-btn" data-mode="noPost"><i class="bi bi-palette"></i> No Post-Processing</button>
            </div>
            <div class="inspector-section">
            <div class="inspector-section-title">Material Channels (7)</div>
            <button class="inspector-btn modal-inspector-btn" data-mode="baseColor"><i class="bi bi-circle-fill"></i> Base Color</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="metalness"><i class="bi bi-lightning-charge"></i> Metalness</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="roughness"><i class="bi bi-hurricane"></i> Roughness</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="emission"><i class="bi bi-brightness-high"></i> Emission</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="normalMap"><i class="bi bi-box"></i> Normal Map</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="specular"><i class="bi bi-circle"></i> Specular F0</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="vertexColor"><i class="bi bi-droplet"></i> Vertex Color</button>
          </div>
          <div class="inspector-section">
            <div class="inspector-section-title">Geometry (4)</div>
            <button class="inspector-btn modal-inspector-btn" data-mode="matcap"><i class="bi bi-record-circle"></i> Matcap</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="matcapSurface"><i class="bi bi-record-circle-fill"></i> Matcap+Surface</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="wireframe"><i class="bi bi-grid-3x3"></i> Wireframe</button>
            <button class="inspector-btn modal-inspector-btn" data-mode="normals"><i class="bi bi-arrows-move"></i> Vertex Normals</button>
          </div>
            <div class="inspector-section">
              <div class="inspector-section-title">UV (1)</div>
              <button class="inspector-btn modal-inspector-btn" data-mode="uv"><i class="bi bi-grid-1x2"></i> UV Checker</button>
            </div>
          </div>
          <div id="viewer"></div>
          <div class="rotate-hint"><i class="bi bi-hand-index-thumb"></i><span>เลื่อน/หมุนเพื่อดูโมเดล</span></div>
          <div id="modelInfo" class="model-info"></div>
        </div>
      </div>
            <div class="modal-footer flex-column align-items-stretch p-4" style="background:#fff;">
        <div class="d-flex flex-wrap justify-content-between align-items-center w-100 mb-3 gap-3 pb-3 border-bottom">
          <div class="d-flex align-items-center">
            <div class="me-3" id="viewerUploaderAvatar" style="width: 48px; height: 48px; background: #eee; color: #333; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.2rem; font-weight: bold; border: 1px solid #ccc;">
              U
            </div>
            <div>
              <h5 class="fw-bold mb-0 text-dark model-title" id="viewerTitle" style="font-size: 1.15rem;">Title</h5>
              <div class="text-muted small mt-1 d-flex align-items-center gap-2">
                <span id="viewerUploaderName" class="fw-semibold">-</span>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <a id="viewerModelPage" href="#" class="btn btn-light border btn-sm text-muted fw-bold d-flex align-items-center" style="letter-spacing: 0.5px;"><i class="bi bi-box-arrow-up-right me-1"></i> รายละเอียด</a>
            <a id="viewerDownload" href="#" class="btn btn-info btn-sm text-white fw-bold d-flex align-items-center px-3" target="_blank" style="letter-spacing: 0.5px;"><i class="bi bi-download me-1"></i> ดาวน์โหลด</a>
          </div>
        </div>
        <p class="model-desc text-secondary small mb-3" id="viewerDesc"></p>
        <!-- Comment section inside modal -->
        <div class="border-top pt-3 mt-1" id="modalCommentSection" style="max-height:220px;overflow-y:auto;">
          <div class="fw-semibold small mb-2"><i class="bi bi-chat-dots me-1"></i>ความคิดเห็น <span id="modalCCount"></span></div>
          <?php if(isset($_SESSION['uid'])): ?>
          <div class="d-flex gap-2 mb-2" id="modalCommentForm">
            <label class="visually-hidden" for="modalCommentBody">ความคิดเห็น</label>
            <textarea id="modalCommentBody" class="form-control form-control-sm" rows="2" placeholder="แสดงความคิดเห็น..." style="resize:none;"></textarea>
            <button id="modalCommentSubmit" class="btn btn-primary btn-sm align-self-end">โพสต์</button>
          </div>
          <?php endif ?>
          <div id="modalCommentList" class="small"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Edit Modal ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">แก้ไขโมเดล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="editForm" class="modal-body pt-2" enctype="multipart/form-data">
        <input type="hidden" name="id"   id="edit_id">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
          <label class="form-label" for="edit_title">ชื่อโมเดล</label>
          <input name="title" id="edit_title" class="form-control" required>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="edit_model">อัปเดตไฟล์โมเดล <small class="text-muted">(ไม่บังคับ)</small></label>
            <input type="file" name="model" id="edit_model" class="form-control" accept=".obj,.glb,.gltf">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit_thumb">อัปเดต Thumbnail <small class="text-muted">(ไม่บังคับ)</small></label>
            <input type="file" name="thumb" id="edit_thumb" class="form-control" accept=".jpg,.jpeg,.png">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="edit_description">คำอธิบาย</label>
          <textarea name="description" id="edit_description" rows="2" class="form-control"></textarea>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label text-success fw-bold" for="edit_price"><i class="bi bi-tag-fill"></i> ตั้งราคา (บาท)</label>
            <input type="number" step="0.01" min="0" name="price" id="edit_price" class="form-control border-success" placeholder="0.00 (ฟรี)">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="edit_license">License</label>
            <select name="license" id="edit_license" class="form-select" style="padding:10px 14px; border-radius:12px; background:#fafbfd; border:1px solid #dfe5ea;">
              <?php foreach($licenses as $l): ?>
              <option value="<?= $l ?>"><?= $l ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="edit_tags">Tags <small class="text-muted">(คั่นด้วยลูกน้ำ)</small></label>
            <input name="tags" id="edit_tags" class="form-control" placeholder="เช่น vehicle, sci-fi">
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button id="editDelete" type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">ลบโมเดลนี้</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button form="editForm" class="btn btn-primary">บันทึกการแก้ไข</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">ยืนยันการลบโมเดล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">ลบโมเดล "<span id="cdm-title"></span>" ?<br><small class="text-muted">ไฟล์และข้อมูลที่เกี่ยวข้องจะถูกลบถาวร</small></div>
      <div class="modal-footer"><button type="button" class="btn btn-danger" id="cdm-confirm">ยืนยัน</button></div>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:12px; overflow:hidden;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark mx-auto w-100 text-center" style="font-size:1.1rem;">ชำระเงินซื้อโมเดล</h5>
        <button type="button" class="btn-close position-absolute end-0 top-0 m-3" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center pt-3 pb-4">
        <p class="text-muted small mb-3" id="paymentInstruction" style="font-size:0.8rem;">สแกน QR Code ด้วยแอปธนาคารหรือ<br>TrueMoney Wallet</p>

        <div id="qrLoading" class="my-4"><i class="bi bi-hourglass-split spinner-border spinner-border-sm text-dark"></i> <span class="small text-muted">กำลังโหลดข้อมูลบัญชีรับเงิน...</span></div>
        <div id="qrError" class="alert alert-danger small py-2 d-none mx-2" style="background-color:#ffe4e6; color:#e11d48; border:none; border-radius:8px;">ผู้ขายยังไม่ได้ตั้งค่าบัญชีรับเงิน</div>

        <img id="qrImage" src="" alt="QR Code" class="img-fluid mb-2 d-none mx-auto d-block border rounded p-1" style="max-height: 200px; max-width: 200px;">

        <div id="bankInfo" class="d-none text-start bg-light p-3 rounded mb-3 border mx-2">
          <div class="small text-muted mb-2">โอนเงินเข้าบัญชีธนาคาร:</div>
          <div class="d-flex align-items-center mb-2">
             <div id="bankIconDisplay" class="d-none me-2 shadow-sm" style="width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; font-weight: bold;"></div>
             <div class="fw-bold text-dark fs-6" id="bankNameDisplay"></div>
          </div>
          <div class="d-flex align-items-center justify-content-between mb-1">
             <div class="fs-6 text-dark fw-bold" id="bankAccountDisplay" style="letter-spacing:1px;"></div>
             <button type="button" class="btn btn-sm btn-outline-dark" onclick="navigator.clipboard.writeText(document.getElementById('bankAccountDisplay').innerText)" title="คัดลอก"><i class="bi bi-clipboard"></i></button>
          </div>
          <div class="small fw-semibold text-dark" id="bankAccountNameDisplay"></div>
        </div>

        <div id="qrPrice" class="fw-bold fs-3 text-dark mb-4 d-none text-center">฿<?= number_format($m['price'] ?? 0, 2) ?></div>

        <form id="paymentForm" class="text-start px-3">
          <input type="hidden" name="model_ids[]" value="<?= $mid ?>">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark" for="slipInput">แนบสลิปโอนเงิน (เฉพาะสลิป)</label>
            <input type="file" name="slip" id="slipInput" class="form-control form-control-sm border shadow-sm" accept="image/*" required>
          </div>
          <button type="submit" class="btn w-100 fw-bold btn-dark shadow-sm" style="border-radius:6px; font-size:0.95rem; padding:0.6rem 0;">ยืนยันการชำระเงิน</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.modal-comment-item{padding:.5rem .7rem;border-radius:6px;background:rgba(255,255,255,.05);margin-bottom:.4rem;}
.modal-comment-item .cname{font-weight:600;color:#fbbf24;}
.modal-comment-item .cbody{color:#d0d0d0;white-space:pre-wrap;word-break:break-word;}
</style>

<script>
// ========== updateModelInfo ==========
window.updateModelInfo = function(root){
  const infoEl = document.getElementById('modelInfo');
  if (!infoEl) return;
  if (!root) { infoEl.textContent = ''; return; }
  let verts = 0, tris = 0, meshes = 0, mats = new Set();
  root.traverse(o => {
    if (o.isMesh) {
      meshes++;
      const g = o.geometry, pos = g?.attributes?.position;
      if (pos) {
        verts += pos.count;
        tris += (g.index ? g.index.count / 3 : pos.count / 3);
      }
      const arr = Array.isArray(o.material) ? o.material : [o.material];
      arr.forEach(m => m && mats.add(m.name || m.type));
    }
  });
  const box = new THREE.Box3().setFromObject(root);
  const s = box.getSize(new THREE.Vector3());
  const sc = root.scale, r = root.rotation, p = root.position;
  infoEl.innerHTML =
  `<div class="mono">Dim: ${s.x.toFixed(3)} × ${s.y.toFixed(3)} × ${s.z.toFixed(3)}</div>
   <div class="mono">Scale: [${sc.x.toFixed(3)}, ${sc.y.toFixed(3)}, ${sc.z.toFixed(3)}]</div>
   <div class="mono">Pos: [${p.x.toFixed(2)}, ${p.y.toFixed(2)}, ${p.z.toFixed(2)}]  RotY: ${THREE.MathUtils.radToDeg(r.y).toFixed(1)}°</div>
   <div class="mono">Meshes: ${meshes} • Materials: ${mats.size}</div>
   <div class="mono">Vertices: ${verts.toLocaleString()} • Triangles: ${Math.round(tris).toLocaleString()}</div>`;
};

// ========== openEditModal ==========
window.openEditModal = function(ds){
  const m = bootstrap.Modal.getOrCreateInstance('#editModal');
  document.getElementById('edit_id').value          = ds.id    || '';
  document.getElementById('edit_title').value       = ds.title || '';
  document.getElementById('edit_price').value       = ds.price || '';
  document.getElementById('edit_description').value = ds.description || '';
  const licEl = document.getElementById('edit_license');
  if(licEl && ds.license) licEl.value = ds.license;
  const tagEl = document.getElementById('edit_tags');
  if(tagEl) tagEl.value = ds.tags || '';
  document.querySelectorAll('#editForm input[type="file"]').forEach(i=>i.value='');
  m.show();
};

// ========== editForm submit ==========
document.getElementById('editForm')?.addEventListener('submit', async e=>{
  e.preventDefault();
  const fd = new FormData(e.currentTarget);
  fd.append('action','update');
  const submitBtn = e.currentTarget.closest('.modal-content').querySelector('.btn.btn-primary');
  submitBtn.disabled=true;
  try{
    const res=await fetch('edit.php',{method:'POST',body:fd});
    if(!res.ok){ alert('บันทึกไม่สำเร็จ ('+res.status+')'); return; }
    const d=await res.json().catch(()=>null);
    if(d?.ok){
      window.dispatchEvent(new CustomEvent('model:edited',{detail:{id:fd.get('id'),title:d.title||fd.get('title')}}));
      bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
    } else { alert(d?.message||'บันทึกไม่สำเร็จ'); }
  }catch(err){ console.error(err); alert('เครือข่ายผิดพลาด'); }
  finally{ submitBtn.disabled=false; }
});

// ========== confirmDeleteModal (from editModal) ==========
document.getElementById('cdm-confirm').addEventListener('click', async ()=>{
  const id=document.getElementById('edit_id').value;
  const csrf=document.querySelector('#editForm input[name="csrf"]')?.value||'';
  const res=await fetch('edit.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'delete',id,csrf})});
  const d=await res.json().catch(()=>null);
  if(d?.ok){
    bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'))?.hide();
    bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
    location.reload();
  } else { alert(d?.message||'ลบไม่สำเร็จ'); }
});

// ========== Modal Viewer Comments ==========
let _modalModelId = null;

window._loadModalComments = async function(modelId){
  _modalModelId = modelId;
  const res = await fetch('comment.php?model_id='+modelId);
  const d   = await res.json();
  const list = document.getElementById('modalCommentList');
  const cnt  = document.getElementById('modalCCount');
  if(!list) return;
  if(!d.comments||!d.comments.length){ list.innerHTML='<p class="text-muted small mb-0">ยังไม่มีความคิดเห็น</p>'; if(cnt) cnt.textContent='(0)'; return; }
  list.innerHTML='';
  d.comments.forEach(c=>{
    const el=document.createElement('div');
    el.className='modal-comment-item'; el.dataset.cid=c.id;
    const canDel=d.uid&&parseInt(d.uid)===parseInt(c.user_id);
    el.innerHTML=`<span class="cname">${escH(c.username)}</span> <span class="text-muted">${c.created_at}</span>${canDel?` <button class="btn btn-sm btn-link text-danger p-0 float-end modal-del-comment" data-id="${c.id}" style="font-size:.75rem"><i class="bi bi-x-circle"></i></button>`:''}<div class="cbody">${escH(c.body)}</div>`;
    list.appendChild(el);
  });
  if(cnt) cnt.textContent='('+d.comments.length+')';
};

document.getElementById('modalCommentSubmit')?.addEventListener('click', async ()=>{
  const body=document.getElementById('modalCommentBody')?.value.trim();
  if(!body||!_modalModelId) return;
  const res=await fetch('comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'add',model_id:_modalModelId,body})});
  const d=await res.json();
  if(d.ok){ document.getElementById('modalCommentBody').value=''; window._loadModalComments(_modalModelId); }
});

document.getElementById('modalCommentList')?.addEventListener('click', async e=>{
  const btn=e.target.closest('.modal-del-comment'); if(!btn) return;
  const res=await fetch('comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=delete&id='+btn.dataset.id});
  const d=await res.json();
  if(d.ok){ btn.closest('.modal-comment-item').remove(); }
});

function escH(t){ const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
</script>
