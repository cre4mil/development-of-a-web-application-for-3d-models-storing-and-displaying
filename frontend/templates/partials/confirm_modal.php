<!-- Generic confirmation dialog, driven by App.confirm() in core.js -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center pt-4">
        <div class="stat-icon red mx-auto mb-3"><i class="bi bi-exclamation-triangle"></i></div>
        <h5 class="modal-title mb-2" id="confirmTitle">ยืนยันการดำเนินการ</h5>
        <p class="text-muted mb-0" id="confirmMessage"></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-danger" id="confirmOk">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>
