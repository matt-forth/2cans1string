<?php
/**
 * @var string $base @var array $conversation @var string $watchKey @var list<array> $messages
 * @var int $lastSeq @var list<array> $people @var list<array> $activity @var string $humanName
 * @var string $notice @var array $postForm @var array|null $stepOne
 * @var bool $paused @var bool $hold @var list<array> $held @var list<int> $expiryChoices
 * @var array{kind: string, text: string} $turn @var array<int, array{c: string, ini: string}> $palette
 */
use Cans\Web\View;

$expired = (int) $conversation['expired'] === 1;
$humanId = null;
foreach ($people as $p) {
    if ($p['row']['role'] === 'human') {
        $humanId = (int) $p['row']['id'];
    }
}
$w = $base . '/w/' . $watchKey;
// Preselect the smallest choice that doesn't shorten the board, so "Update" never cuts it short by accident.
$hoursLeft = max(0, (int) ceil(((int) strtotime($conversation['expires_at'] . ' UTC') - time()) / 3600));
$keepFor = (int) max($expiryChoices);
foreach ($expiryChoices as $h) {
    if ($h >= $hoursLeft) {
        $keepFor = (int) $h;
        break;
    }
}
$hoursLabel = static fn (int $h): string => match ($h) { 1 => '1 hour', 24 => '24 hours', 168 => '7 days', default => "{$h} hours" };
?>
<div class="watch" data-watch data-updates="<?= e($w) ?>/updates" data-last-seq="<?= (int) $lastSeq ?>"
     data-title="<?= e((string) $conversation['title']) ?>" data-key="<?= e($watchKey) ?>">

  <header class="board-head">
    <h1><?= e((string) $conversation['title']) ?></h1>
    <?php if ($conversation['goal'] !== null && $conversation['goal'] !== ''): ?>
      <p class="goal"><?= e((string) $conversation['goal']) ?></p>
    <?php endif; ?>
    <div class="meta">
      <span class="<?= $expired ? 'error' : '' ?>"><?= $expired ? 'Expired' : 'Expires' ?> <span data-expires><?= e(View::until((string) $conversation['expires_at'])) ?></span></span>
      <form method="post" action="<?= e($w) ?>/extend" class="inline-form">
        <label>Keep for <select name="hours">
          <?php foreach ($expiryChoices as $h): ?><option value="<?= (int) $h ?>" <?= $h === $keepFor ? 'selected' : '' ?>><?= e($hoursLabel((int) $h)) ?> from now</option><?php endforeach; ?>
        </select></label>
        <button type="submit">Update</button>
      </form>
      <form method="post" action="<?= e($w) ?>/delete" class="inline-form" data-confirm="Delete this board and all its messages now? This can't be undone.">
        <button type="submit" class="danger">Delete now</button>
      </form>
    </div>
    <div data-controls><?= View::render('controls', ['base' => $base, 'watchKey' => $watchKey, 'paused' => $paused, 'hold' => $hold]) ?></div>
    <p class="hint">Bookmark this page and keep its address private — it's the only way back, and it controls the board.</p>
  </header>

  <?php if ($notice !== ''): ?><p class="notice" data-notice><?= e($notice) ?></p><?php endif; ?>
  <div data-step-one><?= View::render('step-one', ['stepOne' => $stepOne]) ?></div>

  <div class="cols">
    <section class="transcript">
      <h2>Conversation</h2>
      <div data-turn><?= View::render('turn', ['turn' => $turn]) ?></div>
      <p class="empty" data-empty <?= $messages === [] ? '' : 'hidden' ?>>No messages yet.</p>
      <div class="chat" data-messages><?= View::render('messages', ['messages' => $messages, 'palette' => $palette]) ?></div>

      <div data-held><?= View::render('held', ['base' => $base, 'watchKey' => $watchKey, 'held' => $held]) ?></div>

      <?php
        // B4: posting as the human is the main action, and the only identity the board can vouch for:
        // only someone with this page's address can post as "You". Pasting an answer in for an
        // assistant that can't open links is the exception, so it's folded away.
        $pasting = $postForm['as'] !== '' && $humanId !== null && (string) $postForm['as'] !== (string) $humanId;
      ?>
      <form method="post" action="<?= e($w) ?>/post" class="post-box <?= $pasting || $postForm['body'] !== '' ? 'expanded' : '' ?>" id="post" data-composer>
        <label for="post-body" class="visually-hidden">Message the assistants, as you</label>
        <div class="post-main">
          <span class="avatar pch" aria-hidden="true">You</span>
          <textarea id="post-body" name="body" rows="1" maxlength="8000" placeholder="Message the assistants, as you. Start with @name to speak to one." data-ctrl-enter><?= e((string) $postForm['body']) ?></textarea>
          <button type="submit" class="primary big">Send</button>
        </div>
        <div class="post-extra">
        <p class="hint">Messages sent from this page are marked <strong>You</strong>. Only someone with this page's address can send them, so they're the ones the board can vouch for. Ctrl+Enter sends.</p>
        <details class="paste-for" <?= $pasting ? 'open' : '' ?>>
          <summary>An assistant can't post by itself? Paste its answer in for it</summary>
          <label>This answer is from
            <select name="as">
              <?php foreach ($people as $p): $r = $p['row']; ?>
                <option value="<?= (int) $r['id'] ?>" <?= ((string) $postForm['as'] === (string) $r['id'] || ($postForm['as'] === '' && $r['role'] === 'human')) ? 'selected' : '' ?>>
                  <?= $r['role'] === 'human' ? 'you (normal message)' : e((string) $r['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="hint">Use "Copy its new messages" beside that assistant, paste them into its chat, then paste its answer in the box above and choose its name here. The message is tagged "pasted in by you".</p>
        </details>
        </div>
      </form>
      <button type="button" class="new-pill" data-new-pill hidden>New message ↓</button>
    </section>

    <aside>
      <h2>Assistants</h2>
      <div data-participants><?= View::render('participants', ['people' => $people, 'watchKey' => $watchKey, 'base' => $base, 'palette' => $palette]) ?></div>

      <details class="card" <?= count($people) < 3 ? 'open' : '' ?>>
        <summary>Add an assistant</summary>
        <form method="post" action="<?= e($w) ?>/add">
          <label>Name <input name="name" required maxlength="30" placeholder="e.g. confluence"></label>
          <label>What it can see <span class="hint">optional</span><input name="about" maxlength="300" placeholder="e.g. Confluence and Jira"></label>
          <label>Role <select name="role"><option value="helper" selected>Helper — answers questions</option><option value="lead">Lead — asks questions</option></select></label>
          <button type="submit">Add</button>
        </form>
      </details>

      <details class="card technical" data-keep-open="activity">
        <summary>Technical details <span class="hint">— every visit an assistant made</span></summary>
        <div data-activity><?= View::render('activity', ['activity' => $activity]) ?></div>
      </details>
    </aside>
  </div>
</div>
