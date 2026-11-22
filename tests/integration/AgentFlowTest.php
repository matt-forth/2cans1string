<?php

declare(strict_types=1);

// Drives the real app in-process against the configured MySQL database. Each test makes its own
// board and deletes it afterwards.

use Cans\App;
use Cans\Config;
use Cans\Db;
use Cans\Domain\Conversations;
use Cans\Domain\Participants;
use Cans\Http\Request;

/** Calls the app; returns the response body. $target is a path plus raw query, e.g. '/a/k/post?message=hi'. */
function call(string $target, string $method = 'GET', array $post = [], string $ua = 'integration-test', string $accept = 'text/html'): string
{
    [$path, $query] = array_pad(explode('?', $target, 2), 2, '');
    ob_start();
    App::handle(testConfig(), testDb(), new Request($method, $path, $query, $post, $ua, '127.0.0.1', $accept));

    return (string) ob_get_clean();
}

function testConfig(): Config
{
    static $config = null;

    return $config ??= Config::load(dirname(__DIR__, 2));
}

function testDb(): Db
{
    static $db = null;

    return $db ??= new Db(testConfig());
}

/** @return array{id: int, lead: string, helper: string, base: string} */
function newBoard(string $goal = 'Find the retry rules'): array
{
    $config = testConfig();
    $c = (new Conversations(testDb()))->create('Test board', $goal, 1, 'test');
    $p = new Participants(testDb(), $config);
    $p->add($c['id'], 'matt', 'human', null, null);
    $lead = $p->add($c['id'], 'vs-copilot', 'lead', 'the codebase', null);
    $helper = $p->add($c['id'], 'confluence', 'helper', 'Confluence and Jira', null);

    return ['id' => $c['id'], 'lead' => '/a/' . $lead['agent_key'], 'helper' => '/a/' . $helper['agent_key'], 'base' => $config->baseUrl()];
}

function withBoard(callable $body, string $goal = 'Find the retry rules'): void
{
    $board = newBoard($goal);
    try {
        $body($board);
    } finally {
        (new Conversations(testDb()))->delete($board['id']);
    }
}

function has(string $haystack, string $needle): void
{
    ok(str_contains($haystack, $needle), "expected to find:\n    {$needle}\n  in:\n" . preg_replace('/^/m', '    | ', $haystack));
}

function hasNot(string $haystack, string $needle): void
{
    ok(!str_contains($haystack, $needle), "expected NOT to find: {$needle}");
}

test('integration: first visit briefs fully; later visits point to help', function (): void {
    withBoard(function (array $b): void {
        $first = call($b['lead']);
        has($first, 'You are: vs-copilot (lead)   Messages so far: 0');
        has($first, 'YOUR ROLE: LEAD');
        has($first, 'RULES FOR EVERYONE');
        has($first, '  confluence (helper) — Confluence and Jira');
        has($first, '  vs-copilot (lead, you) — the codebase');
        has($first, '  matt (human) — the user');
        has($first, 'GOAL: Find the retry rules');
        has($first, 'No messages yet.');
        has($first, 'Waiting on: confluence (hasn\'t opened the board yet).');
        has($first, 'CHECK FOR NEW MESSAGES: ' . $b['base'] . $b['lead'] . '/read?after=0&r=');

        $second = call($b['lead']);
        hasNot($second, 'YOUR ROLE');
        has($second, '(Full instructions: ' . $b['base'] . $b['lead'] . '/help)');
    });
});

test('integration: post with unencoded &, addressing, relay line, duplicate suppression', function (): void {
    withBoard(function (array $b): void {
        call($b['lead']);
        $posted = call($b['lead'] . '/post?message=@confluence%20Find%20retries%20& backoff%0Arules');
        has($posted, 'Posted as #1. Received 40 characters: "@confluence Find retries & backoff ⏎ rules"');
        has($posted, 'Tell the user: "Ask confluence to check the board."');
        has($posted, 'CHECK FOR NEW MESSAGES: ' . $b['base'] . $b['lead'] . '/read?after=1&r=');

        $again = call($b['lead'] . '/post?message=@confluence%20find%20RETRIES%20%26%20backoff%0Arules');
        has($again, 'Already posted as #1 — not posted again.');

        $helper = call($b['helper']);
        has($helper, 'YOUR ROLE: HELPER');
        has($helper, 'Another assistant (vs-copilot, the lead)');
        has($helper, "#1  vs-copilot → confluence   ");
        has($helper, "@confluence Find retries & backoff\nrules");
    });
});

test('integration: read cursor, nothing-new, waiting-on, meanwhile on post', function (): void {
    withBoard(function (array $b): void {
        call($b['lead']);
        call($b['helper']);
        call($b['lead'] . '/post?message=Question%20one');

        $nothing = call($b['lead'] . '/read?after=1&r=abc');
        has($nothing, 'No new messages since #1.');
        has($nothing, 'Waiting on: confluence (hasn\'t read #1).');

        call($b['helper'] . '/post?message=Answer%20one');
        $read = call($b['lead'] . '/read?after=1&r=def');
        has($read, "#2  confluence   ");
        has($read, 'Answer one');
        has($read, 'Waiting on: nobody — everyone has read everything.');

        // Helper posts twice more; the lead's next post shows them as "Meanwhile".
        call($b['helper'] . '/post?message=Answer%20two');
        call($b['helper'] . '/post?message=Answer%20three');
        $post = call($b['lead'] . '/post', 'POST', ['message' => "Thanks — with a # and\r\nnewline"]);
        has($post, 'Posted as #5.');
        has($post, "Meanwhile:\n\n#3  confluence");
        has($post, 'Answer three');
        $row = testDb()->run('SELECT body, via FROM messages WHERE conversation_id = ? AND seq = 5', [$b['id']])->fetch();
        eq("Thanks — with a # and\nnewline", $row['body']);
        eq('post', $row['via']);

        $lead = testDb()->run("SELECT last_read_seq FROM participants WHERE conversation_id = ? AND name = 'vs-copilot'", [$b['id']])->fetchColumn();
        eq(5, (int) $lead);
    });
});

test('integration: invite adds a helper, returns its starter prompt, is idempotent', function (): void {
    withBoard(function (array $b): void {
        call($b['lead']);
        $out = call($b['lead'] . '/invite?name=M365%20Copilot&about=Teams, meetings & SharePoint');
        has($out, 'Added m365-copilot (Teams, meetings & SharePoint) to the board.');
        has($out, 'Your name on the board is "m365-copilot". vs-copilot is asking for your help.');
        has($out, 'Then post your request for m365-copilot on the board, starting with @m365-copilot.');
        ok(preg_match('#/a/([a-z2-9]{20})#', substr($out, strpos($out, '==========')), $m) === 1, 'prompt has a key');

        $again = call($b['lead'] . '/invite?name=m365-copilot');
        has($again, 'm365-copilot is already on the board');
        has($again, '/a/' . $m[1]);

        $helper = call($b['helper']);
        has($helper, '#1  (system)');
        has($helper, 'vs-copilot added m365-copilot to the board — Teams, meetings & SharePoint.');

        $newcomer = call('/a/' . $m[1]);
        has($newcomer, 'You are: m365-copilot (helper)');

        has(call($b['lead'] . '/invite?name=!'), 'ERROR: Use a short name');
        has(call($b['lead'] . '/invite?name=matt'), 'ERROR: Use a short name');
    });
});

test('integration: errors are 200 text with instructions', function (): void {
    withBoard(function (array $b): void {
        has(call($b['lead'] . '/post?message='), 'ERROR: No message received.');
        has(call($b['lead'] . '/post'), 'ERROR: No message received.');
        has(call('/a/' . str_repeat('a', 20)), "ERROR: This link isn't recognised.");
        has(call('/a/not-a-key'), "ERROR: This link isn't recognised.");
        has(call('/a/' . str_repeat('a', 20) . '/deep/er'), "ERROR: This link isn't recognised.");
        has(call($b['lead'] . '/read/extra'), '(There is no "/read/extra" link — here is the help.)');

        $unknown = call($b['lead'] . '/sned?message=hi');
        has($unknown, '(There is no "/sned" link — here is the help.)');
        has($unknown, 'ALL LINKS');

        $long = call($b['lead'] . '/post', 'POST', ['message' => str_repeat('x', 8001)]);
        has($long, 'ERROR: Message too long (8001 characters; the limit is 8000).');

        has(call($b['lead'] . '/post?message=hi', 'GET', [], 'Mozilla/5.0 (compatible; Slackbot 1.0)'), 'link previews don\'t');
        eq(0, (int) testDb()->run('SELECT COUNT(*) FROM messages WHERE conversation_id = ?', [$b['id']])->fetchColumn(), 'nothing posted');

        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?', [$b['id']]);
        has(call($b['lead']), 'ERROR: This board expired at');
    });
});

test('integration: format=html wraps escaped text; activity is logged', function (): void {
    withBoard(function (array $b): void {
        $html = call($b['lead'] . '?format=html');
        has($html, '<pre>2 CANS 1 STRING');
        has($html, '&amp;r=');

        call($b['lead'] . '/post?message=hello');
        $rows = testDb()->run('SELECT action, outcome FROM activity WHERE conversation_id = ? ORDER BY id', [$b['id']])->fetchAll();
        eq(['start', 'post'], array_column($rows, 'action'));
        eq('posted #1 (5 chars)', $rows[1]['outcome']);
    });
});

test('integration: concurrent-style posts get consecutive seqs; long boards paginate', function (): void {
    withBoard(function (array $b): void {
        for ($i = 1; $i <= 25; $i++) {
            call($b['helper'] . "/post?message=Message%20number%20{$i}");
        }
        $seqs = testDb()->run('SELECT seq FROM messages WHERE conversation_id = ? ORDER BY seq', [$b['id']])->fetchAll(PDO::FETCH_COLUMN);
        eq(range(1, 25), array_map('intval', $seqs));

        $start = call($b['lead']);
        has($start, 'Earlier messages: fetch ' . $b['base'] . $b['lead'] . '/read?after=0&r=');
        hasNot($start, "#5  confluence");
        has($start, "#6  confluence");
        has($start, "#25  confluence");

        $page1 = call($b['lead'] . '/read?after=0');
        has($page1, "#20  confluence");
        hasNot($page1, "#21  confluence");
        has($page1, 'More messages: fetch ' . $b['base'] . $b['lead'] . '/read?after=20&r=');
    });
});
