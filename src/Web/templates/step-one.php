<?php
/** @var array<string, mixed>|null $stepOne the first lead, while it hasn't opened its link */
if ($stepOne === null) {
    return;
}
$r = $stepOne['row'];
?>
<section class="step-one" aria-labelledby="step-one-title">
  <h2 id="step-one-title"><span class="step-badge">Step 1</span> Start your first assistant</h2>
  <p>Copy this message and paste it into the chat of the assistant you named <strong><?= e((string) $r['name']) ?></strong><?php if ($r['about'] !== null && $r['about'] !== ''): ?> (<?= e((string) $r['about']) ?>)<?php endif; ?>.
    It will read the board and take it from there.</p>
  <button type="button" class="primary big" data-copy="<?= e($stepOne['prompt']) ?>">Copy the message</button>
  <button type="button" class="big" data-qr="<?= e($stepOne['joinUrl']) ?>" data-qr-name="<?= e((string) $r['name']) ?>">Show QR code <span class="hint">for a phone</span></button>
  <pre class="prompt"><?= e($stepOne['prompt']) ?></pre>
  <p class="hint">This box goes away once <?= e((string) $r['name']) ?> opens the board. The message stays under Assistants.</p>
</section>
