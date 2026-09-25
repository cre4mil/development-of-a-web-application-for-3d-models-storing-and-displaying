<?php
$accept = '.' . implode(',.', App\Services\Security::MODEL_EXTENSIONS);
?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editTitle"><i class="bi bi-pencil-square me-2 text-primary"></i>แก้ไขโมเดล</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <form id="editForm" class="modal-body" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="edit_title">ชื่อโมเดล</label>
            <input name="title" id="edit_title" class="form-control" required maxlength="255">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit_model">เปลี่ยนไฟล์โมเดล <span class="text-muted fw-normal">(ไม่บังคับ)</span></label>
            <input type="file" name="model" id="edit_model" class="form-control" accept="<?= e($accept) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="edit_thumb">เปลี่ยนภาพปก <span class="text-muted fw-normal">(ไม่บังคับ)</span></label>
            <input type="file" name="thumb" id="edit_thumb" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          </div>
          <div class="col-12">
            <label class="form-label" for="edit_description">คำอธิบาย</label>
            <textarea name="description" id="edit_description" rows="3" class="form-control"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="edit_price">ราคา (บาท)</label>
            <input type="number" step="0.01" min="0" name="price" id="edit_price" class="form-control" placeholder="0 = ฟรี">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="edit_license">License</label>
            <select name="license" id="edit_license" class="form-select">
              <?php foreach (App\Services\ModelManagement::licenseCodes() as $code) : ?>
                <option value="<?= e($code) ?>"><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="edit_tags">Tags <span class="text-muted fw-normal">(คั่นด้วยจุลภาค)</span></label>
            <input name="tags" id="edit_tags" class="form-control">
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button id="editDelete" type="button" class="btn btn-ghost text-danger me-auto" data-action="delete-model-from-edit"><i class="bi bi-trash3 me-1"></i>ลบโมเดล</button>
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button>
        <button form="editForm" type="submit" class="btn btn-primary" id="editSubmit">บันทึกการแก้ไข</button>
      </div>
    </div>
  </div>
</div>
