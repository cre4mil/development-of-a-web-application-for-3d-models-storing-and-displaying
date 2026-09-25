<?php
/**
 * @var list<array<string, mixed>> $mine
 * @var list<array<string, mixed>> $saved
 * @var int $followers
 * @var array{models: int, views: int, likes: int, comments: int} $totals
 * @var array{id: int, name: string, admin: bool} $user
 */
use App\Support\Format;
?>
<div class="page">
  <div class="panel profile-head">
    <span class="avatar avatar-lg"><?= e(Format::initial($user['name'])) ?></span>
    <div class="flex-grow-1">
      <h1><?= e($user['name']) ?></h1>
      <div class="text-muted small"><?= $user['admin'] ? 'ผู้ดูแลระบบ' : 'Creator' ?> · <?= $followers ?> ผู้ติดตาม</div>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-cloud-arrow-up me-1"></i>อัปโหลดโมเดล</button>
      <a href="creator_earnings.php" class="btn btn-soft"><i class="bi bi-cash-stack me-1"></i>รายได้</a>
    </div>
  </div>

  <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-mine" type="button" role="tab"><i class="bi bi-box-seam me-1"></i>โมเดลของฉัน <span class="badge text-bg-light ms-1"><?= count($mine) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-saved" type="button" role="tab"><i class="bi bi-bookmark me-1"></i>บันทึกไว้ <span class="badge text-bg-light ms-1"><?= count($saved) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-stats" type="button" role="tab"><i class="bi bi-bar-chart me-1"></i>สถิติ</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-mine" role="tabpanel">
      <?php if ($mine) : ?>
        <div class="panel">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr><th>โมเดล</th><th>สถานะ</th><th>ราคา</th><th>License</th><th class="text-center">Views</th><th class="text-center">Likes</th><th class="text-end">จัดการ</th></tr>
              </thead>
              <tbody>
                <?php foreach ($mine as $m) : ?>
                  <?php $id = (int) $m['id']; $public = (int) $m['is_public'] === 1; ?>
                  <tr data-row="<?= $id ?>">
                    <td>
                      <a class="cell-model" href="model.php?id=<?= $id ?>">
                        <img class="thumb-sm" src="<?= e(uploadUrl($m['thumb'])) ?>" alt="" loading="lazy">
                        <span class="t"><?= e($m['title']) ?></span>
                      </a>
                    </td>
                    <td><span class="status <?= $public ? 'public' : 'hidden' ?>" data-status><?= $public ? 'แสดงบนหน้าหลัก' : 'ซ่อน' ?></span></td>
                    <td><?= (float) $m['price'] > 0 ? e(money($m['price'])) : 'ฟรี' ?></td>
                    <td><span class="license-chip"><?= e($m['license']) ?></span></td>
                    <td class="text-center"><?= e(Format::compact($m['view_count'])) ?></td>
                    <td class="text-center"><?= (int) $m['like_count'] ?></td>
                    <td class="text-end text-nowrap">
                      <button type="button" class="btn btn-soft btn-sm btn-icon" data-action="edit-model" data-id="<?= $id ?>" title="แก้ไข" aria-label="แก้ไข"><i class="bi bi-pencil"></i></button>
                      <button type="button" class="btn btn-soft btn-sm btn-icon" data-action="toggle-visibility" data-id="<?= $id ?>" data-public="<?= $public ? 1 : 0 ?>" title="แสดง/ซ่อน" aria-label="แสดงหรือซ่อน"><i class="bi <?= $public ? 'bi-eye-slash' : 'bi-eye' ?>"></i></button>
                      <button type="button" class="btn btn-soft btn-sm btn-icon text-danger" data-action="delete-model" data-id="<?= $id ?>" data-title="<?= e($m['title']) ?>" title="ลบ" aria-label="ลบ"><i class="bi bi-trash3"></i></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php else : ?>
        <div class="empty"><i class="bi bi-box-seam"></i><h3>ยังไม่มีโมเดลที่คุณอัปโหลด</h3><p class="mb-0">กดปุ่ม “อัปโหลดโมเดล” เพื่อเริ่มต้น</p></div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-saved" role="tabpanel">
      <?php if ($saved) : ?>
        <div class="model-grid">
          <?php foreach ($saved as $m) : ?>
            <?= partial('model_card', ['m' => $m, 'user' => $user]) ?>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <div class="empty"><i class="bi bi-bookmark"></i><h3>ยังไม่มีโมเดลที่บันทึกไว้</h3><p class="mb-0"><a href="index.php">เริ่มค้นหาโมเดล</a></p></div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-stats" role="tabpanel">
      <div class="stat-grid">
        <div class="stat"><span class="stat-icon"><i class="bi bi-box-seam"></i></span><div><div class="num"><?= $totals['models'] ?></div><div class="lbl">โมเดลทั้งหมด</div></div></div>
        <div class="stat"><span class="stat-icon green"><i class="bi bi-eye"></i></span><div><div class="num"><?= number_format($totals['views']) ?></div><div class="lbl">ยอดเข้าชมรวม</div></div></div>
        <div class="stat"><span class="stat-icon red"><i class="bi bi-heart"></i></span><div><div class="num"><?= number_format($totals['likes']) ?></div><div class="lbl">ยอดถูกใจรวม</div></div></div>
        <div class="stat"><span class="stat-icon violet"><i class="bi bi-chat-dots"></i></span><div><div class="num"><?= number_format($totals['comments']) ?></div><div class="lbl">ความคิดเห็นรวม</div></div></div>
      </div>
    </div>
  </div>
</div>

<?= partial('quickview_modal', ['user' => $user]) ?>
