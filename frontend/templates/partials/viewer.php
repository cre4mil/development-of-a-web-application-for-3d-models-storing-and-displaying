<?php
/**
 * 3D viewer stage (bound by assets/js/viewer.js).
 *
 * @var string $variant page | modal | embed
 * @var string $src     model file URL, empty when it is set later from JavaScript
 * @var string $ext     model file extension
 * @var string $poster  thumbnail URL shown while the model loads
 * @var string $title   model title shown on the loading card
 */
?>
<div class="viewer viewer-<?= e($variant) ?>" data-viewer data-src="<?= e($src) ?>" data-ext="<?= e($ext) ?>">
  <div class="viewer-canvas" data-viewer-canvas></div>

  <div class="viewer-poster" data-viewer-poster>
    <img data-viewer-poster-img src="<?= e($poster) ?>" alt="">
    <div class="poster-box">
      <div class="title" data-viewer-title><?= e($title) ?></div>
      <div class="progress-track"><div class="progress-fill" data-viewer-progress></div></div>
      <div class="progress-label" data-viewer-label>กำลังเตรียมโมเดล…</div>
    </div>
  </div>

  <div class="viewer-error" data-viewer-error>
    <div><i class="bi bi-exclamation-octagon fs-2 d-block mb-2"></i><span data-viewer-error-text>โหลดโมเดลไม่สำเร็จ</span></div>
  </div>

  <div class="viewer-stats" data-viewer-stats hidden></div>
  <div class="viewer-hint" data-viewer-hint><i class="bi bi-hand-index-thumb me-1"></i>ลากเพื่อหมุน · เลื่อนเมาส์เพื่อซูม</div>

  <div class="viewer-toolbar">
    <button type="button" class="viewer-btn" data-viewer-btn="help" title="วิธีใช้งาน" aria-label="วิธีใช้งาน"><i class="bi bi-question-lg"></i></button>
    <button type="button" class="viewer-btn" data-viewer-btn="quality" title="คุณภาพภาพ (HD/SD)" aria-label="คุณภาพภาพ"><i class="bi bi-gear"></i><span class="badge-mini" data-viewer-quality>HD</span></button>
    <button type="button" class="viewer-btn" data-viewer-btn="inspector" title="Model Inspector" aria-label="Model Inspector"><i class="bi bi-layers"></i></button>
    <button type="button" class="viewer-btn" data-viewer-btn="rotate" title="หมุนอัตโนมัติ" aria-label="หมุนอัตโนมัติ"><i class="bi bi-arrow-repeat"></i></button>
    <button type="button" class="viewer-btn" data-viewer-btn="vr" title="VR" aria-label="VR" hidden><i class="bi bi-badge-vr"></i></button>
    <button type="button" class="viewer-btn" data-viewer-btn="fullscreen" title="เต็มจอ" aria-label="เต็มจอ"><i class="bi bi-arrows-fullscreen"></i></button>
  </div>

  <div class="viewer-help" data-viewer-help>
    <h6><i class="bi bi-mouse me-1"></i>ควบคุมมุมมอง</h6>
    <dl>
      <dt>หมุน</dt><dd>คลิกซ้าย + ลาก / ลากด้วยนิ้ว</dd>
      <dt>เลื่อน</dt><dd>คลิกขวา + ลาก / สองนิ้ว</dd>
      <dt>ซูม</dt><dd>ล้อเมาส์ / บีบนิ้ว</dd>
      <dt>รีเซ็ต</dt><dd>ดับเบิลคลิกที่ภาพ</dd>
    </dl>
  </div>

  <aside class="inspector" data-viewer-inspector aria-label="Model Inspector">
    <div class="inspector-head">
      <span><i class="bi bi-box me-2"></i>Model Inspector</span>
      <button type="button" data-viewer-btn="inspector" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="inspector-section">
      <div class="inspector-title">Wireframe Color</div>
      <div class="swatches">
        <button type="button" class="swatch swatch-auto active" data-swatch="default" aria-label="อัตโนมัติ"></button>
        <?php foreach (['#000000', '#cccccc', '#ff0000', '#0000ff', '#00ff00', '#ffff00'] as $color) : ?>
          <button type="button" class="swatch" data-swatch="<?= e($color) ?>" style="background:<?= e($color) ?>" aria-label="<?= e($color) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="inspector-section">
      <label class="switch-row"><span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" data-viewer-single></span>Single Sided</label>
    </div>
    <div class="inspector-section">
      <div class="inspector-title">Render</div>
      <button type="button" class="inspector-btn active" data-mode="final"><i class="bi bi-palette"></i>Final Render</button>
      <button type="button" class="inspector-btn" data-mode="noPost"><i class="bi bi-sun"></i>No Post-Processing</button>
    </div>
    <div class="inspector-section">
      <div class="inspector-title">Material Channels</div>
      <button type="button" class="inspector-btn" data-mode="baseColor"><i class="bi bi-droplet"></i>Base Color</button>
      <button type="button" class="inspector-btn" data-mode="metalness"><i class="bi bi-lightning-charge"></i>Metalness</button>
      <button type="button" class="inspector-btn" data-mode="roughness"><i class="bi bi-hurricane"></i>Roughness</button>
      <button type="button" class="inspector-btn" data-mode="emission"><i class="bi bi-brightness-high"></i>Emission</button>
      <button type="button" class="inspector-btn" data-mode="normalMap"><i class="bi bi-border-outer"></i>Normal Map</button>
      <button type="button" class="inspector-btn" data-mode="opacity"><i class="bi bi-grid-3x3-gap"></i>Opacity</button>
      <button type="button" class="inspector-btn" data-mode="specular"><i class="bi bi-circle-half"></i>Specular F0</button>
      <button type="button" class="inspector-btn" data-mode="vertexColor"><i class="bi bi-palette2"></i>Vertex Color</button>
    </div>
    <div class="inspector-section">
      <div class="inspector-title">Geometry</div>
      <button type="button" class="inspector-btn" data-mode="matcap"><i class="bi bi-record-circle"></i>Matcap</button>
      <button type="button" class="inspector-btn" data-mode="matcapSurface"><i class="bi bi-record-circle-fill"></i>Matcap + Surface</button>
      <button type="button" class="inspector-btn" data-overlay="wireframe"><i class="bi bi-grid-3x3"></i>Wireframe</button>
      <button type="button" class="inspector-btn" data-overlay="normals"><i class="bi bi-arrows-move"></i>Vertex Normals</button>
    </div>
    <div class="inspector-section">
      <div class="inspector-title">UV</div>
      <button type="button" class="inspector-btn" data-mode="uv"><i class="bi bi-grid-1x2"></i>UV Checker</button>
    </div>
  </aside>
</div>
