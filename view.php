<?php if (!defined('VIEW_INCLUDED')) define('VIEW_INCLUDED', true); ?>

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
        <form method="post" action="login.php" class="login-form" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" class="form-control" required autocomplete="new-email" autocorrect="off" spellcheck="false">
          </div>
          <div class="mb-3">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
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
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" name="username" class="form-control rounded-3" required autocomplete="new-username" autocorrect="off" spellcheck="false">
          </div>
          <div class="mb-3">
            <label class="form-label">อีเมล</label>
            <input type="email" name="email" class="form-control rounded-3" required autocomplete="new-email">
          </div>
          <div class="mb-3">
            <label class="form-label">รหัสผ่าน</label>
            <input type="password" name="password" class="form-control rounded-3" required autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">ยืนยันรหัสผ่าน</label>
            <input type="password" name="confirm_password" class="form-control rounded-3" required autocomplete="new-password">
          </div>
         <button type="submit" class="btn signup-btn">สมัครสมาชิก</button>
          <div class="text-center mt-3">
            <a class="btn btn-link d-block text-center mt-2" data-bs-target="#loginModal" data-bs-toggle="modal" data-bs-dismiss="modal">มีบัญชีแล้ว? เข้าสู่ระบบ</a>
            </span>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>


<!-- ===== Upload Modal (เฉพาะคนล็อกอิน) ===== -->
<div class="modal fade upload-modal" id="uploadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload a new model</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
  <form id="uploadForm" method="post" action="uploads.php"
        enctype="multipart/form-data" class="upload-form">
    <div class="mb-3"><label class="form-label">ชื่อโมเดล</label>
      <input name="title" class="form-control" required>
    </div>
    <div class="mb-3"><label class="form-label">ไฟล์โมเดล (.obj/.glb/.gltf)</label>
      <input type="file" name="model" class="form-control" accept=".obj,.glb,.gltf" required>
    </div>
    <div class="mb-3"><label class="form-label">Thumbnail (jpg/png)</label>
      <input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png" required>
    </div>
    <div class="mb-3"><label class="form-label">คำอธิบาย</label>
      <textarea name="description" rows="4" class="form-control"></textarea>
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
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title model-title" id="viewerTitle"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 px-0">
        <div class="viewer-wrap"><div id="viewer"></div>
          <div class="rotate-hint"><i class="bi bi-hand-index-thumb"></i><span>เลื่อน/หมุนเพื่อดูโมเดล</span></div>
          <div id="modelInfo" class="model-info"></div>
        </div>
      </div>
      <div class="modal-footer d-flex align-items-start flex-wrap">
        <p class="model-desc mb-2 me-auto" id="viewerDesc"></p>
        <a id="viewerDownload" href="#" class="btn btn-success" target="_blank" rel="noopener">ดาวน์โหลด</a>
      </div>
    </div>
  </div>
</div>

<!-- ===== Edit Modal (อยู่ใน view.php เพียงครั้งเดียว) ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">แก้ไขโมเดล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="editForm" class="modal-body" enctype="multipart/form-data">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3"><label class="form-label">ชื่อโมเดล</label><input name="title" id="edit_title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">เปลี่ยนไฟล์โมเดล (ไม่บังคับ)</label><input type="file" name="model" class="form-control" accept=".obj,.glb,.gltf"></div>
        <div class="mb-3"><label class="form-label">เปลี่ยน Thumbnail (ไม่บังคับ)</label><input type="file" name="thumb" class="form-control" accept=".jpg,.jpeg,.png"></div>
        <div class="mb-3"><label class="form-label">คำอธิบาย</label><textarea name="description" id="edit_description" rows="4" class="form-control"></textarea></div>
      </form>
      <div class="modal-footer">
        <button id="editDelete"type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">ลบโมเดลนี้</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button form="editForm" class="btn btn-primary">บันทึกการแก้ไข</button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal (reuse ได้ทุกหน้า) -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ยืนยันการลบโมเดล</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">ลบโมเดล "<span id="cdm-title"></span>" ?<br>
        <small class="text-muted">ไฟล์และข้อมูลที่เกี่ยวข้องจะถูกลบถาวร</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="cdm-confirm">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>


<script>
window.openEditModal = function(ds){
  const m = bootstrap.Modal.getOrCreateInstance('#editModal');
  document.getElementById('edit_id').value = ds.id || '';
  document.getElementById('edit_title').value = ds.title || '';
  document.getElementById('edit_description').value = ds.description || '';
  document.querySelectorAll('#editForm input[type="file"]').forEach(i=> i.value='');
  m.show();
};

document.getElementById('editForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();

  const fd = new FormData(e.currentTarget);
  fd.append('action','update'); 

  const submitBtn = e.currentTarget.closest('.modal-content')
                    .querySelector('.btn.btn-primary');
  submitBtn.disabled = true;

  try {
    const res = await fetch('edit.php', { method:'POST', body: fd });
    if (!res.ok) {
      const text = await res.text().catch(()=> '');
      alert(`บันทึกไม่สำเร็จ (${res.status})`);
      console.error('edit.php error response:', text);
      return;
    }

    const data = await res.json().catch(()=> null);
    if (data?.ok) {
      window.dispatchEvent(new CustomEvent('model:edited', {
        detail: { id: fd.get('id'), title: data.title || fd.get('title') }
      }));
      bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
    } else {
      alert(data?.message || 'บันทึกไม่สำเร็จ');
    }
  } catch (err) {
    console.error(err);
    alert('เครือข่ายผิดพลาด');
  } finally {
    submitBtn.disabled = false;
  }
});

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
  const id = document.getElementById('edit_id').value;
  const res = await fetch('delete_model.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `id=${encodeURIComponent(id)}`
  });
  if ((await res.text()).includes('success')) {
    location.reload();
  } else {
    alert('ลบไม่สำเร็จ');
  }
});

</script>

