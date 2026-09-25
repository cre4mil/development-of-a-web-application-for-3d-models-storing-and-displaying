<?php
/**
 * Model detail page (Sketchfab-style viewer + actions).
 *
 * @var array<string, mixed> $model
 * @var list<string> $tags
 * @var string $viewerFile
 * @var string $viewerExt
 * @var int $size
 * @var bool $isOwner
 * @var bool $liked
 * @var bool $saved
 * @var bool $following
 * @var int $followers
 * @var string|null $purchase       approved | pending | null
 * @var bool $canDownload
 * @var list<array<string, mixed>> $formats
 * @var array{name: string, url: ?string} $license
 * @var list<array<string, mixed>> $suggested
 * @var array{id: int, name: string, admin: bool} $user
 */
use App\Support\Format;

$id = (int) $model['id'];
$ownerId = (int) $model['user_id'];
$price = (float) $model['price'];
$loginModal = $user['id'] > 0 ? '' : ' data-bs-toggle="modal" data-bs-target="#loginModal"';
?>
<div class="page" data-model-id="<?= $id ?>" data-owner-id="<?= $ownerId ?>" data-title="<?= e($model['title']) ?>" data-price="<?= $price ?>">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($model['title']) ?></li>
    </ol>
  </nav>

  <div class="row g-4">
    <div class="col-lg-8">
      <?= partial('viewer', [
          'variant' => 'page',
          'src' => 'uploads/' . rawurlencode($viewerFile),
          'ext' => $viewerExt,
          'poster' => uploadUrl($model['thumb']),
          'title' => $model['title'],
      ]) ?>

      <h1 class="model-title"><?= e($model['title']) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center text-muted small">
        <span class="price-badge<?= $price <= 0 ? ' free' : '' ?>"><?= $price <= 0 ? 'Free' : e(money($price)) ?></span>
        <?php if ((int) $model['is_public'] !== 1) : ?><span class="status hidden"><i class="bi bi-eye-slash"></i>ซ่อนจากสาธารณะ</span><?php endif; ?>
        <span>3D Model · เผยแพร่ <?= e(Format::dateTime($model['created_at'])) ?></span>
      </div>

      <div class="creator-row">
        <div class="creator">
          <span class="avatar avatar-lg" style="width:48px;height:48px;font-size:1.1rem"><?= e(Format::initial($model['uploader'])) ?></span>
          <div>
            <div class="name"><?= e($model['uploader']) ?></div>
            <div class="small text-muted"><span data-followers><?= $followers ?></span> ผู้ติดตาม</div>
          </div>
          <?php if (!$isOwner) : ?>
            <button type="button" class="btn btn-sm <?= $following ? 'btn-soft' : 'btn-primary' ?>" data-action="follow" data-user="<?= $ownerId ?>" data-following="<?= $following ? 1 : 0 ?>"><?= $following ? 'กำลังติดตาม' : 'ติดตาม' ?></button>
          <?php endif; ?>
        </div>
        <div class="meta-stats">
          <span title="ยอดเข้าชม"><i class="bi bi-eye"></i><?= e(Format::compact($model['view_count'])) ?></span>
          <span title="ความคิดเห็น"><i class="bi bi-chat-square-text"></i><span id="commentCountTop"><?= (int) $model['comment_count'] ?></span></span>
          <span title="ถูกใจ"><i class="bi bi-heart"></i><span data-like-count><?= (int) $model['like_count'] ?></span></span>
        </div>
      </div>

      <div class="action-bar">
        <?php if ($canDownload) : ?>
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#downloadModal"><i class="bi bi-download me-1"></i>Download 3D Model</button>
        <?php elseif ($purchase === 'pending') : ?>
          <span class="status-pill pending" title="รอผู้ดูแลระบบตรวจสอบสลิป"><i class="bi bi-clock-history"></i>รอตรวจสอบการชำระเงิน</span>
        <?php else : ?>
          <button type="button" class="btn btn-primary btn-sm"<?= $user['id'] > 0 ? ' data-bs-toggle="modal" data-bs-target="#paymentModal"' : $loginModal ?>><i class="bi bi-cart2 me-1"></i>Buy Model (<?= e(money($price)) ?>)</button>
        <?php endif; ?>

        <?php if ($isOwner) : ?>
          <button type="button" class="btn btn-ghost btn-sm" data-action="edit-model" data-id="<?= $id ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</button>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost btn-sm<?= $saved ? ' is-active' : '' ?>" data-action="save" data-id="<?= $id ?>" data-saved="<?= $saved ? 1 : 0 ?>"><i class="bi <?= $saved ? 'bi-bookmark-fill' : 'bi-bookmark' ?> me-1"></i><span data-label><?= $saved ? 'บันทึกแล้ว' : 'บันทึก' ?></span></button>
        <button type="button" class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#embedModal"><i class="bi bi-code-slash me-1"></i>Embed</button>
        <button type="button" class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#shareModal"><i class="bi bi-share me-1"></i>Share</button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-ghost btn-sm<?= $liked ? ' is-liked' : '' ?>" data-action="like" data-id="<?= $id ?>" data-liked="<?= $liked ? 1 : 0 ?>"><i class="bi <?= $liked ? 'bi-heart-fill' : 'bi-heart' ?> me-1"></i><span data-label><?= $liked ? 'Liked' : 'Like' ?></span></button>
      </div>

      <div class="d-flex flex-wrap gap-3 align-items-center text-muted small py-3">
        <span><i class="bi bi-triangle me-1"></i>Triangles: <strong class="text-dark" data-stat="triangles">—</strong></span>
        <span><i class="bi bi-diagram-3 me-1"></i>Vertices: <strong class="text-dark" data-stat="vertices">—</strong></span>
        <button type="button" class="btn btn-link btn-sm p-0" data-action="toggle-info">ข้อมูลโมเดลเพิ่มเติม</button>
      </div>
      <div class="info-panel" id="infoPanel">
        <dl class="info-grid mb-0">
          <div><dt>Triangles</dt><dd data-stat="triangles">—</dd></div>
          <div><dt>Vertices</dt><dd data-stat="vertices">—</dd></div>
          <div><dt>Meshes</dt><dd data-stat="meshes">—</dd></div>
          <div><dt>Materials</dt><dd data-stat="materials">—</dd></div>
          <div><dt>Textures</dt><dd data-stat="textures">—</dd></div>
          <div><dt>ขนาดโมเดล</dt><dd data-stat="dimensions">—</dd></div>
          <div><dt>ไฟล์ต้นฉบับ</dt><dd><?= e(strtoupper(pathinfo((string) $model['filename'], PATHINFO_EXTENSION))) ?> · <?= e(Format::bytes($size)) ?></dd></div>
          <div><dt>License</dt><dd><?= e($model['license']) ?></dd></div>
        </dl>
      </div>

      <?php if ((string) $model['description'] !== '') : ?>
        <p class="model-desc mt-3"><?= e($model['description']) ?></p>
      <?php endif; ?>

      <p class="text-muted small mt-3">
        License: <strong class="text-dark"><?= e($model['license']) ?></strong> — <?= e($license['name']) ?>
        <?php if ($license['url'] !== null) : ?> · <a href="<?= e($license['url']) ?>" target="_blank" rel="noopener noreferrer">ดูรายละเอียด</a><?php endif; ?>
      </p>

      <?php if ($tags) : ?>
        <div class="mb-4">
          <?php foreach ($tags as $tag) : ?><a class="tag-pill" href="index.php?tag=<?= e(rawurlencode($tag)) ?>">#<?= e($tag) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <section class="mt-4" id="comments">
        <h2 class="h5 mb-3"><i class="bi bi-chat-dots me-2"></i>ความคิดเห็น <span class="text-muted fw-normal" id="commentCount">(<?= (int) $model['comment_count'] ?>)</span></h2>
        <?php if ($user['id'] > 0) : ?>
          <form id="commentForm" class="d-flex gap-3 mb-3">
            <span class="avatar"><?= e(Format::initial($user['name'])) ?></span>
            <div class="flex-grow-1">
              <label class="visually-hidden" for="commentBody">ความคิดเห็น</label>
              <textarea id="commentBody" class="form-control" rows="2" maxlength="1000" placeholder="เขียนความคิดเห็น…"></textarea>
              <button type="submit" class="btn btn-primary btn-sm mt-2">โพสต์</button>
            </div>
          </form>
        <?php else : ?>
          <div class="alert alert-light border mb-3"><a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">เข้าสู่ระบบ</a> เพื่อแสดงความคิดเห็น</div>
        <?php endif; ?>
        <div id="commentList"><p class="text-muted">กำลังโหลดความคิดเห็น…</p></div>
      </section>
    </div>

    <aside class="col-lg-4">
      <div class="eyebrow mb-3">Suggested 3D models</div>
      <div class="d-flex flex-column gap-1">
        <?php foreach ($suggested as $s) : ?>
          <a class="suggest" href="model.php?id=<?= (int) $s['id'] ?>">
            <div class="suggest-thumb">
              <img src="<?= e(uploadUrl($s['thumb'])) ?>" alt="" loading="lazy">
              <?php if ((float) $s['price'] > 0) : ?><span class="price-badge"><?= e(money($s['price'])) ?></span><?php endif; ?>
            </div>
            <div class="min-w-0">
              <div class="t"><?= e($s['title']) ?></div>
              <div class="m"><?= e($s['uploader']) ?></div>
              <div class="m"><i class="bi bi-eye"></i> <?= e(Format::compact($s['view_count'])) ?> · <i class="bi bi-chat-square-text"></i> <?= (int) $s['comment_count'] ?> · <i class="bi bi-heart"></i> <?= (int) $s['like_count'] ?></div>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (!$suggested) : ?><div class="text-muted small">ยังไม่มีโมเดลอื่นให้แนะนำ</div><?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<!-- Download -->
<div class="modal fade" id="downloadModal" tabindex="-1" aria-labelledby="downloadTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="downloadTitle">Available downloads</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body py-2">
        <?php foreach ($formats as $format) : ?>
          <div class="format-row">
            <div>
              <div class="fw-bold"><?= e($format['ext']) ?> <span class="text-muted fw-normal small ms-1"><?= e($format['label']) ?></span></div>
              <div class="text-muted small">.<?= e(strtolower($format['ext'])) ?> · <?= e(Format::bytes($format['size'])) ?></div>
            </div>
            <a class="btn btn-primary btn-sm" href="download.php?id=<?= $id ?>&amp;format=<?= e($format['format']) ?>">DOWNLOAD</a>
          </div>
        <?php endforeach; ?>
        <?php if (!$formats) : ?><p class="text-muted text-center my-4">ไม่พบไฟล์สำหรับดาวน์โหลด</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Share -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="shareTitle">Share</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body">
        <label class="form-label" for="shareLink">ลิงก์โมเดล</label>
        <div class="copy-field mb-3">
          <input type="text" id="shareLink" class="form-control" readonly>
          <button type="button" class="btn btn-primary" data-action="copy" data-target="#shareLink"><i class="bi bi-clipboard"></i></button>
        </div>
        <div class="share-row">
          <a class="btn btn-soft btn-sm" id="shareFb" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook me-1"></i>Facebook</a>
          <a class="btn btn-soft btn-sm" id="shareX" target="_blank" rel="noopener noreferrer"><i class="bi bi-twitter-x me-1"></i>X</a>
          <a class="btn btn-soft btn-sm" id="shareLine" target="_blank" rel="noopener noreferrer"><i class="bi bi-chat-dots me-1"></i>LINE</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Embed -->
<div class="modal fade" id="embedModal" tabindex="-1" aria-labelledby="embedTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="embedTitle">Embed</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body">
        <label class="form-label" for="embedCode">ฝังโมเดลนี้ในเว็บไซต์ของคุณ</label>
        <textarea id="embedCode" class="form-control mono" rows="4" readonly></textarea>
        <button type="button" class="btn btn-primary btn-sm mt-3" data-action="copy" data-target="#embedCode"><i class="bi bi-clipboard me-1"></i>คัดลอกโค้ด</button>
      </div>
    </div>
  </div>
</div>

<?php if (!$canDownload && $purchase === null && $user['id'] > 0) : ?>
  <!-- Payment -->
  <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="paymentTitle">ชำระเงินซื้อโมเดล</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body text-center">
          <p class="text-muted small mb-3" id="paymentHint">สแกน QR Code ด้วยแอปธนาคาร</p>
          <div id="qrLoading" class="my-4 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลบัญชีรับเงิน…</div>
          <div id="qrError" class="alert alert-danger small py-2 d-none">ยังไม่ได้ตั้งค่าบัญชีรับเงิน</div>
          <div id="qrBox" class="d-none mx-auto mb-2 p-2 border rounded bg-white" style="width: 216px"></div>
          <div id="bankInfo" class="d-none text-start bg-light p-3 rounded border mb-3">
            <div class="small text-muted mb-1">โอนเงินเข้าบัญชี</div>
            <div class="fw-bold" id="bankName"></div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="mono fs-6" id="bankAccount"></span>
              <button type="button" class="btn btn-soft btn-sm btn-icon" data-action="copy" data-target="#bankAccount" aria-label="คัดลอกเลขบัญชี"><i class="bi bi-clipboard"></i></button>
            </div>
            <div class="small" id="bankHolder"></div>
          </div>
          <div class="fs-3 fw-bold mb-3 d-none" id="qrPrice"><?= e(money($price)) ?></div>
          <form id="paymentForm" class="text-start" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="model_ids[]" value="<?= $id ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label class="form-label" for="slipInput">แนบสลิปโอนเงิน</label>
            <input type="file" name="slip" id="slipInput" class="form-control form-control-sm mb-3" accept="image/*" required>
            <button type="submit" class="btn btn-dark w-100">ยืนยันการชำระเงิน</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
