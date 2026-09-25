<?php
/**
 * Gallery home page.
 *
 * @var array<string, string> $filters
 * @var list<array<string, mixed>> $models
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var list<string> $allTags
 * @var list<string> $licenses
 * @var array<string, string> $query
 * @var array{id: int, name: string, admin: bool} $user
 */
$dateOptions = ['' => 'ทุกช่วงเวลา', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้'];
$sortOptions = ['' => $filters['search'] !== '' ? 'ความเกี่ยวข้อง' : 'ค่าเริ่มต้น', 'likes' => 'ถูกใจมากที่สุด', 'views' => 'ยอดชมมากที่สุด', 'recent' => 'ล่าสุด'];
$selected = ' selected';
$without = static fn (string $key): string => 'index.php?' . http_build_query(array_diff_key($query, [$key => true]));
?>
<div class="page">
  <form method="get" action="index.php" class="toolbar" id="filterForm">
    <div class="search-cell">
      <label class="form-label" for="filter_search"><i class="bi bi-search"></i>ค้นหา</label>
      <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" id="filter_search" name="search" class="form-control" placeholder="ชื่อ รายละเอียด หรือแท็ก…" value="<?= e($filters['search']) ?>">
      </div>
    </div>
    <div>
      <label class="form-label" for="filter_tag"><i class="bi bi-tag"></i>หมวดหมู่</label>
      <select id="filter_tag" name="tag" class="form-select" data-autosubmit>
        <option value="">ทั้งหมด</option>
        <?php foreach ($allTags as $tag) : ?>
          <option value="<?= e($tag) ?>"<?= $filters['tag'] === $tag ? $selected : '' ?>>#<?= e($tag) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label" for="filter_date"><i class="bi bi-calendar-event"></i>วันที่อัปโหลด</label>
      <select id="filter_date" name="date" class="form-select" data-autosubmit>
        <?php foreach ($dateOptions as $value => $label) : ?>
          <option value="<?= e($value) ?>"<?= $filters['date'] === $value ? $selected : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label" for="filter_license"><i class="bi bi-shield-check"></i>สัญญาอนุญาต</label>
      <select id="filter_license" name="license" class="form-select" data-autosubmit>
        <option value="">ทั้งหมด</option>
        <?php foreach ($licenses as $license) : ?>
          <option value="<?= e($license) ?>"<?= $filters['license'] === $license ? $selected : '' ?>><?= e($license) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label" for="filter_sort"><i class="bi bi-sort-down"></i>จัดเรียง</label>
      <select id="filter_sort" name="sort" class="form-select" data-autosubmit>
        <?php foreach ($sortOptions as $value => $label) : ?>
          <option value="<?= e($value) ?>"<?= $filters['sort'] === $value ? $selected : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>ค้นหา</button>
      <a href="index.php" class="btn btn-soft" title="ล้างตัวกรอง" aria-label="ล้างตัวกรอง"><i class="bi bi-arrow-counterclockwise"></i></a>
    </div>
  </form>

  <div class="result-line">
    <span>พบ <strong class="text-dark"><?= number_format($total) ?></strong> โมเดล<?= $pages > 1 ? ' · หน้า ' . $page . '/' . $pages : '' ?></span>
    <span class="d-flex flex-wrap gap-2">
      <?php foreach ($query as $key => $value) : ?>
        <span class="chip"><?= e($key === 'sort' ? ($sortOptions[$value] ?? $value) : $value) ?> <a href="<?= e($without($key)) ?>" aria-label="ลบตัวกรอง"><i class="bi bi-x-lg"></i></a></span>
      <?php endforeach; ?>
    </span>
  </div>

  <?php if ($models) : ?>
    <div class="model-grid" id="model-grid">
      <?php foreach ($models as $m) : ?>
        <?= partial('model_card', ['m' => $m, 'user' => $user]) ?>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1) : ?>
      <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'query' => $query]) ?>
    <?php endif; ?>
  <?php else : ?>
    <div class="empty">
      <i class="bi bi-box-seam"></i>
      <h3>ไม่พบโมเดลที่ตรงกับเงื่อนไข</h3>
      <p class="mb-0">ลองเปลี่ยนคำค้นหา หรือ <a href="index.php">ล้างตัวกรองทั้งหมด</a></p>
    </div>
  <?php endif; ?>
</div>

<?= partial('quickview_modal', ['user' => $user]) ?>
