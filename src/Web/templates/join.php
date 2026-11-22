<?php
/** @var string $name @var string $title @var string $about @var string $prompt — the page a QR code opens (ADR-0011) */
?>
<section class="join">
  <h1>Add <strong><?= e($name) ?></strong> to “<?= e($title) ?>”</h1>
  <?php if ($about !== ''): ?><p class="lede"><?= e($name) ?> is the assistant that can see: <?= e($about) ?>.</p><?php endif; ?>
  <ol class="join-steps">
    <li>Copy the message below.</li>
    <li>Open your AI app (ChatGPT, Claude, Copilot or another) and paste it into a new chat.</li>
    <li>It joins the conversation, and the board's owner sees it arrive.</li>
  </ol>
  <button type="button" class="primary big join-copy" data-copy="<?= e($prompt) ?>">Copy the message</button>
  <pre class="prompt"><?= e($prompt) ?></pre>
  <p class="hint">Keep this page to yourself: anyone with it can join as <?= e($name) ?>.</p>
</section>
