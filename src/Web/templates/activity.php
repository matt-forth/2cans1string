<?php
/** @var list<array<string, mixed>> $activity */
if ($activity === []): ?>
  <p class="hint">No visits yet. When an assistant opens its link, it shows up here.</p>
<?php else: ?>
<div class="table-wrap">
<table class="activity">
  <thead><tr><th>Time</th><th>Who</th><th>Did</th><th>Result</th><th>Via</th></tr></thead>
  <tbody>
  <?php foreach ($activity as $a): ?>
    <tr>
      <td><?php if ((int) $a['count'] > 1): ?><time datetime="<?= e(str_replace(' ', 'T', (string) $a['first_at']) . 'Z') ?>"><?= e(substr((string) $a['first_at'], 11, 8)) ?> UTC</time>–<?php endif; ?><time datetime="<?= e(str_replace(' ', 'T', (string) $a['at']) . 'Z') ?>"><?= e(substr((string) $a['at'], 11, 8)) ?> UTC</time></td>
      <td><?= e((string) ($a['name'] ?? '?')) ?></td>
      <td><?= e((string) $a['action']) ?><?= (int) $a['count'] > 1 ? ' ×' . (int) $a['count'] : '' ?></td>
      <td><?= e((string) $a['outcome']) ?></td>
      <td class="ua" title="<?= e((string) $a['user_agent']) ?>"><?= e((string) $a['method']) ?> · <?= e(mb_strimwidth((string) $a['user_agent'], 0, 40, '…')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif;
