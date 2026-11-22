<?php
/**
 * The conversation as chat bubbles (B1): a colour and initials per participant, "from → to" on each.
 *
 * @var list<array<string, mixed>> $messages @var array<int, array{c: string, ini: string}> $palette
 */
foreach ($messages as $m):
    $seq = (int) $m['seq'];
    $iso = str_replace(' ', 'T', (string) $m['created_at']) . 'Z';
    $time = substr((string) $m['created_at'], 11, 5) . ' UTC';
    if ($m['author_name'] === null): ?>
<article class="msg system" id="m<?= $seq ?>">
  <span class="body"><?= e((string) $m['body']) ?></span>
  <time datetime="<?= e($iso) ?>"><?= e($time) ?></time>
</article>
<?php
        continue;
    endif;
    $human = $m['author_role'] === 'human';
    $relay = !$human && $m['via'] === 'relay';
    $p = $palette[(int) $m['participant_id']] ?? ['c' => '0', 'ini' => '?'];
    $to = $m['to_names'] === null ? 'everyone' : str_replace(',', ', ', (string) $m['to_names']);
?>
<article class="msg <?= $human ? 'mine' : 'theirs' ?> pc<?= e($p['c']) ?>" id="m<?= $seq ?>">
  <span class="avatar" aria-hidden="true"><?= e($p['ini']) ?></span>
  <div class="bubble">
    <header>
      <span class="who"><?= $human ? 'You' : e((string) $m['author_name']) ?></span><?php if ($human): ?> <span class="tag owner" title="Sent from the board page, which only the board's owner has">✓ board owner</span><?php endif; ?>
      <span class="to">→ <?= e($to) ?></span>
      <?php if ($relay): ?><span class="tag">pasted in by you</span><?php endif; ?>
      <?php if ($m['author_removed_at'] !== null): ?><span class="tag">removed</span><?php endif; ?>
      <time datetime="<?= e($iso) ?>"><?= e($time) ?></time>
      <span class="seq" title="Message number — assistants refer to messages by it">#<?= $seq ?></span>
    </header>
    <?php $long = mb_strlen((string) $m['body']) > 600 || substr_count((string) $m['body'], "\n") >= 8; // term-a #21: clamp walls of text ?>
    <div class="body<?= $long ? ' clamp' : '' ?>"><?= e((string) $m['body']) ?></div>
    <?php if ($long): ?><button type="button" class="show-more" data-show-more>Show more</button><?php endif; ?>
  </div>
</article>
<?php endforeach;
