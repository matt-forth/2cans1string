<?php

declare(strict_types=1);

// The human pages, driven in-process (helpers call(), testDb(), has() are in AgentFlowTest.php).

use Cans\Domain\Conversations;

/** Creates a board through the real form post; returns [conversation id, watch path, lead agent path]. */
function webBoard(array $fields = []): array
{
    $title = 'Web test ' . bin2hex(random_bytes(4));
    call('/new', 'POST', $fields + ['title' => $title, 'goal' => 'Test goal', 'lead' => 'VS Copilot', 'about' => 'the code', 'hours' => '24']);
    $row = testDb()->run('SELECT id, watch_key FROM conversations WHERE title = ?', [$fields['title'] ?? $title])->fetch();
    ok($row !== false, 'board was created');
    $lead = testDb()->run("SELECT agent_key FROM participants WHERE conversation_id = ? AND role = 'lead'", [$row['id']])->fetchColumn();

    return [(int) $row['id'], '/w/' . $row['watch_key'], '/a/' . $lead];
}

function withWebBoard(callable $body, array $fields = []): void
{
    [$id, $watch, $lead] = webBoard($fields);
    try {
        $body($id, $watch, $lead);
    } finally {
        (new Conversations(testDb()))->delete($id);
    }
}

test('web: home explains, has the start form, links the guide; guide renders', function (): void {
    $home = call('/');
    has($home, 'A shared message board for your AI assistants');
    has($home, '<form method="post" action="' . testConfig()->baseUrl() . '/new">');
    has($home, '/how">Read the full guide');
    has(call('/how'), "When an assistant can't open links");
});

test('web: create validates title and name, re-showing what was typed', function (): void {
    $out = call('/new', 'POST', ['title' => '', 'goal' => 'keep me', 'lead' => 'x', 'hours' => '24']);
    has($out, 'Give the board a title.');
    has($out, 'keep me');
    has(call('/new', 'POST', ['title' => 'T', 'lead' => '!', 'hours' => '24']), 'The first assistant needs a short name');
    has(call('/new', 'POST', ['title' => 'T', 'lead' => 'human', 'hours' => '24']), 'That name is taken by you');
});

test('web: watch page shows the board, starter prompt, nudge status, no activity yet', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        $page = call($watch);
        has($page, '<h1>Web test');
        has($page, 'Test goal');
        has($page, '<strong>vs-copilot</strong>');
        has($page, 'Hasn&#039;t opened the board yet');
        has($page, 'Please fetch ' . testConfig()->baseUrl() . $lead . ' now');
        has($page, 'No messages yet.');
        has($page, 'No visits yet.');

        call($lead);
        $after = call($watch);
        has($after, 'Up to date.');
        has($after, '<td>start</td>');
    });
});

test('web: everything user-supplied is escaped (a script in a message would leak the watch key)', function (): void {
    $x = '<script>alert(1)</script>';
    withWebBoard(function (int $id, string $watch, string $lead) use ($x): void {
        call($lead . '/post?message=' . rawurlencode($x));
        call($watch . '/add', 'POST', ['name' => 'helper', 'about' => $x]);
        $page = call($watch);
        hasNot($page, $x);
        has($page, '&lt;script&gt;alert(1)&lt;/script&gt;');
    }, ['title' => 'T ' . $x, 'goal' => $x, 'about' => $x, 'lead' => 'lead', 'hours' => '24']);
});

test('web: post as human, relay as an agent (advances its cursor), updates JSON', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        $human = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'human'", [$id])->fetchColumn();
        $agent = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'lead'", [$id])->fetchColumn();

        call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => "@vs-copilot hello\r\nthere"]);
        call($watch . '/post', 'POST', ['as' => (string) $agent, 'body' => 'relayed answer']);
        $rows = testDb()->run('SELECT seq, via, to_names, body FROM messages WHERE conversation_id = ? ORDER BY seq', [$id])->fetchAll();
        eq(['web', 'relay'], array_column($rows, 'via'));
        eq('vs-copilot', $rows[0]['to_names']);
        eq("@vs-copilot hello\nthere", $rows[0]['body']);
        eq(2, (int) testDb()->run('SELECT last_read_seq FROM participants WHERE id = ?', [$agent])->fetchColumn());

        has(call($lead), '#2  vs-copilot (via ');

        $json = json_decode(call($watch . '/updates?after=1'), true);
        eq(2, $json['lastSeq']);
        eq(1, $json['newCount']);
        has($json['messagesHtml'], 'relayed answer');
        hasNot($json['messagesHtml'], 'hello');

        has(call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => '  ']), 'Type a message first.');
    });
});

test('web: add, duplicate name refused, remove revokes the link, extend, delete', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        call($watch . '/add', 'POST', ['name' => 'Confluence', 'about' => 'Docs', 'role' => 'helper']);
        $helper = testDb()->run("SELECT id, agent_key FROM participants WHERE conversation_id = ? AND name = 'confluence'", [$id])->fetch();
        ok($helper !== false, 'helper added');
        has(call($watch . '/add', 'POST', ['name' => 'confluence']), 'There&#039;s already someone called confluence');

        call($watch . '/remove', 'POST', ['id' => (string) $helper['id']]);
        has(call('/a/' . $helper['agent_key']), 'ERROR: The user has removed you from this board.');
        hasNot(call($watch), '<strong>confluence</strong>');

        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() + INTERVAL 1 HOUR WHERE id = ?', [$id]);
        call($watch . '/extend', 'POST', ['hours' => '168']);
        $hoursLeft = (int) testDb()->run('SELECT TIMESTAMPDIFF(HOUR, UTC_TIMESTAMP(), expires_at) FROM conversations WHERE id = ?', [$id])->fetchColumn();
        ok($hoursLeft >= 167, "extended to 7 days (got {$hoursLeft} h)");

        call($watch . '/delete', 'POST');
        eq(0, (int) testDb()->run('SELECT COUNT(*) FROM conversations WHERE id = ?', [$id])->fetchColumn());
        has(call($watch), "This board doesn't exist");
        eq(['gone' => true], json_decode(call($watch . '/updates'), true));
    });
});

test('web: a bad watch key is a plain not-found page', function (): void {
    has(call('/w/nope'), "This board doesn't exist");
    has(call('/w/' . str_repeat('a', 20)), "This board doesn't exist");
});
