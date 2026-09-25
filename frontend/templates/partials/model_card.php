<?php
/**
 * One gallery card.
 *
 * @var array<string, mixed> $m prepared by App\Controllers\ModelCards
 * @var array{id: int, name: string, admin: bool} $user
 */
use App\Support\Format;

$id = (int) $m['id'];
$free = (float) $m['price'] <= 0;
$quick = [
    'id' => $id,
    'title' => $m['title'],
    'desc' => (string) $m['description'],
    'file' => 'uploads/' . rawurlencode($m['viewer_file']),
    'ext' => $m['viewer_ext'],
    'thumb' => uploadUrl($m['thumb']),
    'uploader' => $m['uploader'],
    'date' => Format::dateTime($m['created_at']),
    'size' => Format::bytes($m['size']),
    'license' => $m['license'] ?: 'All Rights Reserved',
    'views' => (int) $m['view_count'],
    'likes' => (int) $m['like_count'],
    'liked' => $m['liked'],
    'saved' => $m['saved'],
    'tags' => $m['tags'],
];
?>
<article class="model-card" data-card="<?= $id ?>">
  <div class="card-badges">
    <span class="price-badge<?= $free ? ' free' : '' ?>"><?= $free ? 'Free' : e(money($m['price'])) ?></span>
    <?php if ((int) $m['is_public'] !== 1) : ?><span class="hidden-badge"><i class="bi bi-eye-slash me-1"></i>ซ่อน</span><?php endif; ?>
  </div>

  <div class="card-actions">
    <div class="dropdown">
      <button type="button" class="btn btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="เมนูโมเดล"><i class="bi bi-three-dots"></i></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item fw-semibold" href="model.php?id=<?= $id ?>"><i class="bi bi-box-arrow-up-right"></i>ดูรายละเอียดโมเดล</a></li>
        <li><button type="button" class="dropdown-item" data-action="quickview" data-model="<?= e(json_encode($quick, JSON_UNESCAPED_UNICODE)) ?>"><i class="bi bi-badge-3d"></i>ดูโมเดล 3D ที่นี่</button></li>
        <?php if ($user['id'] > 0) : ?>
          <li><button type="button" class="dropdown-item<?= $m['saved'] ? ' text-primary' : '' ?>" data-action="save" data-id="<?= $id ?>" data-saved="<?= $m['saved'] ? '1' : '0' ?>"><i class="bi <?= $m['saved'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i><span data-label><?= $m['saved'] ? 'บันทึกแล้ว' : 'บันทึก' ?></span></button></li>
        <?php endif; ?>
        <li><a class="dropdown-item" href="download.php?id=<?= $id ?>"><i class="bi bi-download"></i>ดาวน์โหลด</a></li>
        <?php if ($m['mine']) : ?>
          <li><hr class="dropdown-divider"></li>
          <li><button type="button" class="dropdown-item" data-action="edit-model" data-id="<?= $id ?>"><i class="bi bi-pencil"></i>แก้ไข</button></li>
          <li><button type="button" class="dropdown-item" data-action="toggle-visibility" data-id="<?= $id ?>" data-public="<?= (int) $m['is_public'] ?>"><i class="bi <?= (int) $m['is_public'] === 1 ? 'bi-eye-slash' : 'bi-eye' ?>"></i><span data-label><?= (int) $m['is_public'] === 1 ? 'ซ่อนโมเดล' : 'แสดงโมเดล' ?></span></button></li>
          <li><button type="button" class="dropdown-item text-danger" data-action="delete-model" data-id="<?= $id ?>" data-title="<?= e($m['title']) ?>"><i class="bi bi-trash3"></i>ลบ</button></li>
        <?php endif; ?>
      </ul>
    </div>
    <button type="button" class="btn btn-icon<?= $m['liked'] ? ' is-liked' : '' ?>" data-action="like" data-id="<?= $id ?>" data-liked="<?= $m['liked'] ? '1' : '0' ?>" aria-label="ถูกใจ"><i class="bi <?= $m['liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i></button>
  </div>

  <a href="model.php?id=<?= $id ?>" class="card-thumb" aria-label="<?= e($m['title']) ?>">
    <img src="<?= e(uploadUrl($m['thumb'])) ?>" alt="<?= e($m['title']) ?>" loading="lazy">
    <span class="play"><span><i class="bi bi-play-fill"></i> ดูโมเดล 3D</span></span>
  </a>

  <div class="card-body">
    <div>
      <a class="card-title" href="model.php?id=<?= $id ?>"><?= e($m['title']) ?></a>
      <div class="card-author mt-1"><span class="avatar avatar-sm"><?= e(Format::initial($m['uploader'])) ?></span><span><?= e($m['uploader']) ?></span></div>
    </div>
    <?php if ($m['tags']) : ?>
      <div class="tag-row">
        <?php foreach (array_slice($m['tags'], 0, 4) as $tag) : ?><a href="index.php?tag=<?= e(rawurlencode($tag)) ?>">#<?= e($tag) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="card-foot">
      <div class="card-stats">
        <span title="ยอดเข้าชม"><i class="bi bi-eye"></i><?= e(Format::compact($m['view_count'])) ?></span>
        <span title="ความคิดเห็น"><i class="bi bi-chat-square-text"></i><?= (int) $m['comment_count'] ?></span>
        <span title="ถูกใจ"><i class="bi bi-heart"></i><span data-like-count><?= (int) $m['like_count'] ?></span></span>
      </div>
      <span class="license-chip"><?= e($m['license'] ?: 'All Rights Reserved') ?></span>
    </div>
  </div>
</article>
