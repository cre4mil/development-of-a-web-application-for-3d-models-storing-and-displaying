<?php
/**
 * @var int $page
 * @var int $pages
 * @var array<string, string> $query active filters, preserved in every link
 */
$url = static fn (int $target): string => 'index.php?' . http_build_query([...$query, 'page' => $target]);
$from = max(1, $page - 2);
$to = min($pages, $page + 2);
?>
<nav aria-label="การแบ่งหน้า" class="mt-4">
  <ul class="pagination justify-content-center flex-wrap">
    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($url(max(1, $page - 1))) ?>" aria-label="ก่อนหน้า"><i class="bi bi-chevron-left"></i></a></li>
    <?php for ($n = $from; $n <= $to; $n++) : ?>
      <li class="page-item<?= $n === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e($url($n)) ?>"><?= $n ?></a></li>
    <?php endfor; ?>
    <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($url(min($pages, $page + 1))) ?>" aria-label="ถัดไป"><i class="bi bi-chevron-right"></i></a></li>
  </ul>
</nav>
