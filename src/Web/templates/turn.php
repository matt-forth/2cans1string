<?php
/** @var array{kind: string, text: string} $turn */
?>
<p class="turn turn-<?= e($turn['kind']) ?>" role="status"><?= e($turn['text']) ?></p>
