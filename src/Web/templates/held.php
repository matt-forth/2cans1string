<?php
/** @var string $base @var string $watchKey @var list<array<string, mixed>> $held */
if ($held === []) {
    return;
}
$w = $base . '/w/' . $watchKey;
?>
<section class="held" aria-labelledby="held-title">
  <h2 id="held-title">Waiting for your approval <span class="count"><?= count($held) ?></span></h2>
  <p class="hint">Only you can see these. Nobody else sees a message until you approve it.</p>
  <?php foreach ($held as $h): $iso = str_replace(' ', 'T', (string) $h['created_at']) . 'Z'; ?>
    <article class="held-msg">
      <header>
        <span class="who"><?= e((string) $h['author_name']) ?></span>
        <time datetime="<?= e($iso) ?>"><?= e(substr((string) $h['created_at'], 11, 5)) ?> UTC</time>
      </header>
      <div class="body"><?= e((string) $h['body']) ?></div>
      <div class="actions">
        <form method="post" action="<?= e($w) ?>/approve" class="inline-form">
          <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
          <button type="submit" class="primary">✓ Approve and deliver</button>
        </form>
        <form method="post" action="<?= e($w) ?>/discard" class="inline-form">
          <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
          <button type="submit" class="danger">Discard</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</section>
