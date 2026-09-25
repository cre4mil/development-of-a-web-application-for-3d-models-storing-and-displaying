<?php
$formats = App\Services\UploadValidator::modelFormats();
$accept = '.' . implode(',.', App\Services\Security::MODEL_EXTENSIONS);
?>
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadTitle"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>อัปโหลดโมเดลใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <form id="uploadForm" class="modal-body" method="post" action="api/models.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="row g-4">
          <div class="col-md-6">
            <label class="form-label" for="upload_model">ไฟล์โมเดล <span class="text-danger">*</span></label>
            <div class="dropzone" data-dropzone>
              <i class="bi bi-box-seam"></i>
              <div class="picked" data-picked>ลากไฟล์มาวาง หรือคลิกเพื่อเลือก</div>
              <small><?= e($formats) ?></small>
              <input type="file" id="upload_model" name="model" accept="<?= e($accept) ?>" required aria-label="ไฟล์โมเดล">
            </div>
            <div class="form-text">แนะนำ .glb — รวมพื้นผิวไว้ในไฟล์เดียว แสดงผลได้สมบูรณ์ที่สุด</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="upload_thumb">ภาพปก (Thumbnail) <span class="text-muted fw-normal">— สร้างอัตโนมัติจากโมเดล</span></label>
            <img class="thumb-preview" id="thumbPreview" alt="ตัวอย่างภาพปก">
            <input type="file" id="upload_thumb" name="thumb" class="form-control mt-2" accept=".jpg,.jpeg,.png,.webp">
          </div>
          <div class="col-12">
            <label class="form-label" for="upload_title">ชื่อโมเดล <span class="text-danger">*</span></label>
            <input id="upload_title" name="title" class="form-control" required maxlength="255">
          </div>
          <div class="col-12">
            <label class="form-label" for="upload_description">คำอธิบาย</label>
            <textarea id="upload_description" name="description" rows="3" class="form-control"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="upload_price">ราคา (บาท)</label>
            <input type="number" step="0.01" min="0" id="upload_price" name="price" class="form-control" placeholder="0 = ฟรี">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="upload_license">License</label>
            <select id="upload_license" name="license" class="form-select">
              <?php foreach (App\Services\ModelManagement::licenseCodes() as $code) : ?>
                <option value="<?= e($code) ?>"><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="upload_tags">Tags <span class="text-muted fw-normal">(คั่นด้วยจุลภาค)</span></label>
            <input id="upload_tags" name="tags" class="form-control" placeholder="vehicle, sci-fi">
          </div>
        </div>
        <div class="upload-progress mt-4" id="uploadProgress">
          <div class="d-flex justify-content-between small mb-1"><span id="uploadStatus">กำลังอัปโหลด…</span><span id="uploadPercent">0%</span></div>
          <div class="progress-track"><div class="progress-fill" id="uploadBar"></div></div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button>
        <button form="uploadForm" type="submit" class="btn btn-primary" id="uploadSubmit"><i class="bi bi-cloud-arrow-up me-1"></i>อัปโหลด</button>
      </div>
    </div>
  </div>
</div>
