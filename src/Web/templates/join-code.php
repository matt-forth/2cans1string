<?php
/** @var string $base @var string $watchKey @var array{code: string, expires: string}|null $joinCode (ADR-0012) */
use Cans\Web\View;

$w = $base . '/w/' . $watchKey;
?>
<div class="card join-code-card">
  <h2>Join with a code</h2>
  <?php if ($joinCode === null): ?>
    <p class="hint">Someone can type a short code on this site's home page to ask to join. You let each one in.</p>
    <form method="post" action="<?= e($w) ?>/code" class="inline-form">
      <input type="hidden" name="do" value="start">
      <button type="submit">Get a join code</button>
    </form>
  <?php else: ?>
    <p class="join-code" aria-label="Join code"><?= e($joinCode['code']) ?></p>
    <p class="hint">On this site's home page: <strong>Join with a code</strong>. Works for <?= e(substr(View::until($joinCode['expires']), 3)) ?> more; you approve everyone who asks.</p>
    <form method="post" action="<?= e($w) ?>/code" class="inline-form">
      <input type="hidden" name="do" value="stop">
      <button type="submit" class="quiet">Stop this code</button>
    </form>
  <?php endif; ?>
</div>
