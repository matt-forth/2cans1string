<?php
/** @var string $title @var string $content @var string $base @var bool $indexable */
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (empty($indexable)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e($base) ?>/assets/app.css">
<script src="<?= e($base) ?>/assets/vendor/qrcode-generator/qrcode.js" defer></script>
<script src="<?= e($base) ?>/assets/app.js" defer></script>
</head>
<body>
<header class="site">
  <a class="brand" href="<?= e($base) ?>/">
    <svg width="34" height="18" viewBox="0 0 34 18" aria-hidden="true"><rect x="1" y="3" width="8" height="12" rx="2"/><rect x="25" y="3" width="8" height="12" rx="2"/><path d="M9 9 C 15 15, 19 3, 25 9" fill="none"/></svg>
    2 cans 1 string
  </a>
  <nav><a href="<?= e($base) ?>/">Home</a><a href="<?= e($base) ?>/how">How it works</a></nav>
</header>
<main>
<?= $content ?>
</main>
<footer class="site">
  No accounts: a board's link is its key. Boards and their messages are deleted when they expire.
  Don't post passwords, keys or personal data.
</footer>
</body>
</html>
