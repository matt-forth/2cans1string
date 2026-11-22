<?php
/**
 * @var list<array<string, mixed>> $people @var string $watchKey @var string $base
 * @var array<int, array{c: string, ini: string}> $palette
 */
use Cans\Web\View;

foreach ($people as $p):
    $r = $p['row'];
    $id = (int) $r['id'];
    $human = $r['role'] === 'human';
    $c = $palette[$id] ?? ['c' => '0', 'ini' => '?'];
?>
<div class="person pc<?= e($c['c']) ?> <?= $p['nudge'] ? 'nudge' : '' ?>">
  <div class="person-head">
    <span class="avatar" aria-hidden="true"><?= e($c['ini']) ?></span>
    <strong><?= e((string) $r['name']) ?></strong>
    <span class="tag"><?= $human ? 'you' : ($r['role'] === 'lead' ? 'lead — asks' : 'helper — answers') ?></span>
    <?php if (!$human && $r['last_seen_at'] !== null): ?><span class="seen">active <?= e(View::ago($r['last_seen_at'])) ?></span><?php endif; ?>
  </div>
  <?php if ($r['about'] !== null && $r['about'] !== ''): ?><div class="about"><?= e((string) $r['about']) ?></div><?php endif; ?>
  <?php if (!$human): ?>
    <div class="status"><?= e($p['status']) ?></div>
    <div class="actions">
      <button type="button" data-copy="<?= e($p['prompt']) ?>">Copy its starter message</button>
      <?php if ($p['copyForAgent'] !== ''): ?>
        <button type="button" data-copy="<?= e($p['copyForAgent']) ?>" title="For an assistant that can't open links: paste this into its chat, then paste its answer in below">Copy its new messages</button>
      <?php endif; ?>
      <form method="post" action="<?= e($base) ?>/w/<?= e($watchKey) ?>/remove" class="inline-form"
            data-confirm="Remove <?= e((string) $r['name']) ?>? Its link will stop working; its messages stay.">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="quiet">Remove</button>
      </form>
    </div>
    <details data-keep-open="prompt-<?= $id ?>">
      <summary>Show starter message</summary>
      <pre class="prompt"><?= e($p['prompt']) ?></pre>
    </details>
  <?php endif; ?>
</div>
<?php endforeach;
