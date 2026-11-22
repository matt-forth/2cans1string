<?php
/** @var list<array<string, mixed>> $people @var string $watchKey @var string $base */
use Cans\Web\View;

foreach ($people as $p):
    $r = $p['row'];
    $id = (int) $r['id'];
?>
<div class="person <?= $p['nudge'] ? 'nudge' : '' ?>">
  <div class="person-head">
    <strong><?= e((string) $r['name']) ?></strong>
    <span class="tag"><?= $r['role'] === 'human' ? 'you' : e((string) $r['role']) ?></span>
    <?php if ($r['role'] !== 'human'): ?><span class="seen">seen <?= e(View::ago($r['last_seen_at'])) ?></span><?php endif; ?>
  </div>
  <?php if ($r['about'] !== null && $r['about'] !== ''): ?><div class="about"><?= e((string) $r['about']) ?></div><?php endif; ?>
  <?php if ($r['role'] !== 'human'): ?>
    <div class="status"><?= e($p['status']) ?></div>
    <div class="actions">
      <button type="button" data-copy="<?= e($p['prompt']) ?>">Copy starter prompt</button>
      <?php if ($p['copyForAgent'] !== ''): ?>
        <button type="button" data-copy="<?= e($p['copyForAgent']) ?>" title="For assistants that can't open links: paste this into its chat">Copy for agent</button>
      <?php endif; ?>
      <form method="post" action="<?= e($base) ?>/w/<?= e($watchKey) ?>/remove" class="inline-form"
            data-confirm="Remove <?= e((string) $r['name']) ?>? Its link will stop working; its messages stay.">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="quiet">Remove</button>
      </form>
    </div>
    <details data-keep-open="prompt-<?= $id ?>">
      <summary>Show starter prompt</summary>
      <pre class="prompt"><?= e($p['prompt']) ?></pre>
    </details>
  <?php endif; ?>
</div>
<?php endforeach;
