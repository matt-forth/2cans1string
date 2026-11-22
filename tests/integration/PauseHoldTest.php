<?php

declare(strict_types=1);

// Pause and hold-before-send (ADR-0010). Helpers call(), withBoard(), withWebBoard(), has() live in
// AgentFlowTest.php and WebFlowTest.php.

use Cans\Domain\Conversations;
use Cans\Domain\HeldMessages;
use Cans\Domain\Messages;
use Cans\Web\WebController;

test('pause: refuses agent posts and invites, opens every response; resume clears it (B2)', function (): void {
    withBoard(function (array $b): void {
        (new Conversations(testDb()))->setPaused($b['id'], true);
        $start = call($b['lead']);
        ok(str_starts_with($start, "The board is paused by the user: don't post, tell the user you're waiting."), 'opens with the pause line');
        has(call($b['lead'] . '/read?after=0'), 'The board is paused by the user');
        $post = call($b['lead'] . '/post?message=hello');
        has($post, "NOT POSTED. The board is paused by the user: don't post, tell the user you're waiting.");
        has($post, 'CHECK FOR NEW MESSAGES:');
        has(call($b['lead'] . '/invite?name=jira'), 'NOT POSTED.');
        eq(0, (int) testDb()->run('SELECT COUNT(*) FROM messages WHERE conversation_id = ?', [$b['id']])->fetchColumn(), 'nothing posted');
        eq(2, (int) testDb()->run("SELECT COUNT(*) FROM participants WHERE conversation_id = ? AND role <> 'human'", [$b['id']])->fetchColumn(), 'nobody invited');
        $outcomes = testDb()->run("SELECT outcome FROM activity WHERE conversation_id = ? AND action = 'post'", [$b['id']])->fetchAll(PDO::FETCH_COLUMN);
        eq(['refused: board paused'], $outcomes);

        (new Conversations(testDb()))->setPaused($b['id'], false);
        hasNot(call($b['lead']), 'paused');
        has(call($b['lead'] . '/post?message=hello'), 'Posted as #1.');
    });
});

test('hold: held posts get no seq; the poster hears waiting, approved, discarded (B3, #11)', function (): void {
    withBoard(function (array $b): void {
        $held = new HeldMessages(testDb(), new Messages(testDb(), testConfig()));
        (new Conversations(testDb()))->setHold($b['id'], true);
        call($b['lead']);
        call($b['helper']);

        $out = call($b['helper'] . '/post?message=@vs-copilot%20First%20answer');
        has($out, "Held for the user's approval. Received 24 characters:");
        hasNot($out, 'Posted as');
        has(call($b['helper'] . '/post?message=@vs-copilot%20first%20ANSWER'), "Already waiting for the user's approval");
        call($b['helper'] . '/post?message=Second%20answer');
        eq(0, (int) testDb()->run('SELECT COUNT(*) FROM messages WHERE conversation_id = ?', [$b['id']])->fetchColumn(), 'nothing on the board yet');
        has(call($b['lead'] . '/read?after=0'), 'No new messages since #0.');

        $waiting = call($b['helper'] . '/read?after=0');
        has($waiting, 'YOUR HELD MESSAGES');
        has($waiting, "is waiting for the user's approval: \"@vs-copilot First answer\"");

        [$first, $second] = $held->waiting($b['id']);
        eq(1, $held->approve($b['id'], (int) $first['id'], ['vs-copilot', 'confluence']));
        eq(null, $held->approve($b['id'], (int) $first['id'], ['vs-copilot', 'confluence']), 'approving twice does nothing');
        ok($held->discard($b['id'], (int) $second['id']), 'discarded');
        ok(!$held->discard($b['id'], (int) $second['id']), 'discarding twice does nothing');

        $lead = call($b['lead'] . '/read?after=0');
        has($lead, '#1  confluence → vs-copilot');
        hasNot($lead, 'Second answer');

        $news = call($b['helper'] . '/read?after=1');
        has($news, 'The user approved your message from');
        has($news, 'It is #1 on the board.');
        has($news, 'The user discarded your message from');
        has($news, 'Rethink it, or ask the user why.');
        hasNot(call($b['helper'] . '/read?after=1'), 'YOUR HELD MESSAGES');

        // Later posts get the next gap-free seq.
        (new Conversations(testDb()))->setHold($b['id'], false);
        has(call($b['lead'] . '/post?message=Thanks'), 'Posted as #2.');
    });
});

test('web: Pause/Resume post as the human every time; review toggle, approve, discard (B2, B3)', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        has(call($watch), 'Pause the assistants');
        call($watch . '/pause', 'POST');
        $page = call($watch);
        has($page, '<strong>Paused.</strong>');
        has($page, '▶ Resume');
        has(call($lead), 'The board is paused by the user');
        call($watch . '/pause', 'POST'); // already paused: no second message
        call($watch . '/resume', 'POST');
        call($watch . '/pause', 'POST'); // again within ten minutes: must still show
        $bodies = testDb()->run('SELECT body FROM messages WHERE conversation_id = ? ORDER BY seq', [$id])->fetchAll(PDO::FETCH_COLUMN);
        eq([WebController::PAUSE_MESSAGE, WebController::RESUME_MESSAGE, WebController::PAUSE_MESSAGE], $bodies);
        call($watch . '/resume', 'POST');

        has(call($watch), "Review messages before they're delivered: <strong>Off</strong>");
        call($watch . '/hold', 'POST', ['hold' => 'on']);
        has(call($watch), "Review messages before they're delivered: <strong>On</strong>");
        call($lead . '/post?message=' . rawurlencode('Draft <b>answer</b>'));
        call($lead . '/post?message=Bad%20idea');
        $page = call($watch);
        has($page, 'Waiting for your approval <span class="count">2</span>');
        has($page, 'Draft &lt;b&gt;answer&lt;/b&gt;');
        hasNot($page, 'Draft <b>answer</b>');
        $json = json_decode(call($watch . '/updates?after=0'), true);
        has($json['heldHtml'], 'Approve and deliver');

        $heldIds = testDb()->run('SELECT id FROM held_messages WHERE conversation_id = ? ORDER BY id', [$id])->fetchAll(PDO::FETCH_COLUMN);
        call($watch . '/approve', 'POST', ['id' => (string) $heldIds[0]]);
        call($watch . '/discard', 'POST', ['id' => (string) $heldIds[1]]);
        $last = testDb()->run('SELECT seq, body, via FROM messages WHERE conversation_id = ? ORDER BY seq DESC LIMIT 1', [$id])->fetch();
        eq([5, 'Draft <b>answer</b>', 'get'], [(int) $last['seq'], $last['body'], $last['via']]);
        hasNot(call($watch), 'Waiting for your approval');
        call($watch . '/approve', 'POST', ['id' => (string) $heldIds[1]]);
        eq(5, (int) testDb()->run('SELECT MAX(seq) FROM messages WHERE conversation_id = ?', [$id])->fetchColumn(), 'a discarded message cannot be approved');
    });
});

test('web: the start form can turn review on from the beginning (B3)', function (): void {
    withWebBoard(function (int $id): void {
        eq(1, (int) testDb()->run('SELECT hold_agent_posts FROM conversations WHERE id = ?', [$id])->fetchColumn());
    }, ['title' => 'Held ' . bin2hex(random_bytes(4)), 'lead' => 'lead', 'hours' => '24', 'hold' => 'on']);
});
