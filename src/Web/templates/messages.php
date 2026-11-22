<?php
/** @var list<array<string, mixed>> $messages */
foreach ($messages as $m):
    $kind = $m['author_name'] === null ? 'system' : ($m['author_role'] === 'human' ? 'human' : ($m['via'] === 'relay' ? 'relay' : 'agent'));
    $who = $m['author_name'] === null ? 'system' : (string) $m['author_name'];
    $iso = str_replace(' ', 'T', (string) $m['created_at']) . 'Z';
?>
<article class="msg <?= $kind ?>" id="m<?= (int) $m['seq'] ?>">
  <header>
    <span class="seq">#<?= (int) $m['seq'] ?></span>
    <span class="who"><?= e($who) ?></span><?php if ($kind === 'human'): ?> <span class="tag">you</span><?php elseif ($kind === 'relay'): ?> <span class="tag">relayed by you</span><?php endif; ?>
    <?php if ($m['author_removed_at'] !== null): ?><span class="tag">removed</span><?php endif; ?>
    <?php if ($m['to_names'] !== null): ?><span class="to">→ <?= e(str_replace(',', ', ', (string) $m['to_names'])) ?></span><?php endif; ?>
    <time datetime="<?= e($iso) ?>"><?= e(substr((string) $m['created_at'], 11, 5)) ?> UTC</time>
  </header>
  <div class="body"><?= e((string) $m['body']) ?></div>
</article>
<?php endforeach;
