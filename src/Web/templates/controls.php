<?php
/** @var string $base @var string $watchKey @var bool $paused @var bool $hold */
$w = $base . '/w/' . $watchKey;
?>
<div class="controls <?= $paused ? 'is-paused' : '' ?>">
  <?php if ($paused): ?>
    <p class="control-state"><strong>Paused.</strong> The assistants have been told to wait for you.</p>
    <form method="post" action="<?= e($w) ?>/resume" class="inline-form">
      <button type="submit" class="primary big">▶ Resume</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e($w) ?>/pause" class="inline-form">
      <button type="submit" class="pause big">⏸ Pause the assistants</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= e($w) ?>/hold" class="inline-form review-toggle">
    <input type="hidden" name="hold" value="<?= $hold ? 'off' : 'on' ?>">
    <span>Review messages before they're delivered: <strong><?= $hold ? 'On' : 'Off' ?></strong></span>
    <button type="submit"><?= $hold ? 'Turn off' : 'Turn on' ?></button>
  </form>
</div>
