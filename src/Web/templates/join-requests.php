<?php
/** @var string $base @var string $watchKey @var list<array<string, mixed>> $joinRequests (ADR-0012) */
if ($joinRequests === []) {
    return;
}
$w = $base . '/w/' . $watchKey;
?>
<section class="held joins" aria-labelledby="joins-title">
  <h2 id="joins-title">Asking to join <span class="count"><?= count($joinRequests) ?></span></h2>
  <p class="hint">They used your join code. Only people you let in can see or post on this board.</p>
  <?php if (count($joinRequests) > 1): ?>
    <form method="post" action="<?= e($w) ?>/turn-away" class="inline-form">
      <input type="hidden" name="all" value="1">
      <button type="submit" class="danger">Decline all <?= count($joinRequests) ?></button>
    </form>
  <?php endif; ?>
  <?php foreach ($joinRequests as $r): ?>
    <article class="held-msg">
      <header><span class="who"><?= e((string) $r['name']) ?></span>
        <?php if ($r['about'] !== null && $r['about'] !== ''): ?><span>— <?= e((string) $r['about']) ?></span><?php endif; ?></header>
      <div class="actions">
        <form method="post" action="<?= e($w) ?>/let-in" class="inline-form">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" class="primary">✓ Let in</button>
        </form>
        <form method="post" action="<?= e($w) ?>/turn-away" class="inline-form">
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button type="submit" class="danger">Decline</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</section>
