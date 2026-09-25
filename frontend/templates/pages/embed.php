<?php
/**
 * Bare viewer for <iframe> embedding.
 *
 * @var array<string, mixed> $model
 * @var string $viewerFile
 * @var string $viewerExt
 */
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($model['title']) ?> – 3D Gallery</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="embed-body">
  <?= partial('viewer', [
      'variant' => 'embed',
      'src' => 'uploads/' . rawurlencode($viewerFile),
      'ext' => $viewerExt,
      'poster' => uploadUrl($model['thumb']),
      'title' => $model['title'],
  ]) ?>
  <a class="btn btn-sm btn-dark position-fixed" style="left: 12px; top: 12px; z-index: 20" href="model.php?id=<?= (int) $model['id'] ?>" target="_blank" rel="noopener noreferrer">
    <i class="bi bi-box me-1"></i><?= e($model['title']) ?>
  </a>
  <script src="<?= e(asset('js/viewer.js')) ?>"></script>
</body>
</html>
