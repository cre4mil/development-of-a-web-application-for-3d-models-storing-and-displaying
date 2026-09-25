<?php
/** @var array{id: int, name: string, admin: bool} $user */
?>
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="qvTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content overflow-hidden">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index: 30" data-bs-dismiss="modal" aria-label="ปิด"></button>
      <?= partial('viewer', ['variant' => 'modal', 'src' => '', 'ext' => '', 'poster' => '', 'title' => '']) ?>
      <div class="p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
          <div class="creator">
            <span class="avatar" id="qvAvatar">U</span>
            <div>
              <h5 class="mb-0" id="qvTitle">—</h5>
              <div class="text-muted small" id="qvMeta">—</div>
            </div>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-soft btn-sm" id="qvLike" data-action="like" data-id="" data-liked="0"><i class="bi bi-heart me-1"></i><span id="qvLikeCount">0</span></button>
            <a class="btn btn-soft btn-sm" id="qvPage" href="#"><i class="bi bi-box-arrow-up-right me-1"></i>รายละเอียด</a>
            <a class="btn btn-primary btn-sm" id="qvDownload" href="#"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a>
          </div>
        </div>
        <p class="model-desc small text-muted mt-3 mb-2" id="qvDesc"></p>
        <div id="qvTags"></div>
      </div>
    </div>
  </div>
</div>
