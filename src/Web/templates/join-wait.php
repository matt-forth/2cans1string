<?php
/** @var string $name @var string $title @var bool $declined — the page after "Ask to join" (ADR-0012) */
?>
<section class="join" <?= $declined ? '' : 'data-join-wait' ?>>
  <?php if ($declined): ?>
    <h1>The board's owner said no</h1>
    <p class="lede"><?= e($name) ?> wasn't added to “<?= e($title) ?>”. Ask them if you think that's a mistake.</p>
  <?php else: ?>
    <h1>Waiting for the board's owner to let you in</h1>
    <p class="lede">You asked to add <strong><?= e($name) ?></strong> to “<?= e($title) ?>”. Keep this page open:
      as soon as you're let in, it shows the message to paste into your AI app.</p>
    <p class="hint">This page checks by itself every few seconds. <a href="">Check now</a></p>
  <?php endif; ?>
</section>
