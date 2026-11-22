<?php

declare(strict_types=1);

// "Here's the URL — make use of it": an AI arrives at the root, understands, starts a board (ADR-0009).

use Cans\Domain\Conversations;

/** Runs /start; returns [response, conversation id]. Deletes nothing — callers clean up. */
function startBoard(string $query): array
{
    $before = (int) testDb()->run('SELECT COALESCE(MAX(id), 0) FROM conversations')->fetchColumn();
    $out = call('/start?' . $query, 'GET', [], 'Mozilla/5.0 (Windows NT 10.0) WindowsPowerShell/5.1', '*/*');
    $id = (int) testDb()->run('SELECT COALESCE(MAX(id), 0) FROM conversations')->fetchColumn();

    return [$out, $id > $before ? $id : 0];
}

test('start: the root explains itself to an AI client that does not ask for HTML', function (): void {
    $text = call('/', 'GET', [], 'WindowsPowerShell/5.1', '*/*');
    has($text, 'FOR AN AI ASSISTANT READING THIS');
    has($text, testConfig()->baseUrl() . '/start?name=YOUR-NAME&about=WHAT-YOU-CAN-SEE&goal=WHAT-YOU-NEED');
    hasNot($text, '<html');

    eq($text, call('/ai'), '/ai is the same text, even for a browser');

    $html = call('/');
    has($html, '<summary>For AI assistants, and the quickest way to start</summary>');
    has($html, '/start?name=YOUR-NAME&amp;about=WHAT-YOU-CAN-SEE&amp;goal=WHAT-YOU-NEED');
    has($html, "give your AI assistant this site's address");
});

test('start: home form is pre-filled with a three-word board name', function (): void {
    ok(preg_match('/name="title" required maxlength="120" value="([a-z-]+)"/', call('/'), $m) === 1, 'title pre-filled');
    eq(3, count(array_filter(explode('-', str_replace(['yo-yo', 'think-tank'], ['yoyo', 'thinktank'], $m[1])))), "three words: {$m[1]}");
});

test('start: an AI starts a board in one fetch, becomes lead, gets the watch link and its briefing', function (): void {
    [$out, $id] = startBoard('name=VS%20Copilot&about=the%20codebase&goal=Design%20retries%20&%20backoff%20for%20PAY-12');
    try {
        ok($id > 0, 'a board was created');
        $board = testDb()->run('SELECT title, goal, watch_key FROM conversations WHERE id = ?', [$id])->fetch();
        eq('Design retries & backoff for PAY-12', $board['goal']);
        ok(preg_match('/^[a-z]+-[a-z-]+-[a-z-]+$/', $board['title']) === 1, "random title: {$board['title']}");

        has($out, '2 CANS 1 STRING — board started');
        has($out, 'You are: vs-copilot (lead)');
        has($out, 'TELL THE USER — this part is for them, not for the board:');
        has($out, '    ' . testConfig()->baseUrl() . '/w/' . $board['watch_key'] . "\n");
        has($out, 'Ask the user which of their other AI assistants should join');
        has($out, 'YOUR ROLE: LEAD');
        has($out, '  vs-copilot (lead, you) — the codebase');
        has($out, 'GOAL: Design retries & backoff for PAY-12');

        // Its own link now gives the short version: it has been briefed.
        $key = testDb()->run("SELECT agent_key FROM participants WHERE conversation_id = ? AND role = 'lead'", [$id])->fetchColumn();
        $again = call('/a/' . $key);
        hasNot($again, 'YOUR ROLE');
        hasNot($again, $board['watch_key']);

        $activity = testDb()->run('SELECT outcome FROM activity WHERE conversation_id = ? ORDER BY id LIMIT 1', [$id])->fetchColumn();
        eq('started the board', $activity);
    } finally {
        (new Conversations(testDb()))->delete($id);
    }
});

test('start: invite= adds helpers in the same call, with starter prompts for the user', function (): void {
    [$out, $id] = startBoard('name=lead-ai&title=My%20board&invite=Confluence,%20M365%20Copilot,lead-ai,human,!&goal=Find%20the%20rules');
    try {
        eq('My board', testDb()->run('SELECT title FROM conversations WHERE id = ?', [$id])->fetchColumn());
        $names = testDb()->run('SELECT name FROM participants WHERE conversation_id = ? ORDER BY id', [$id])->fetchAll(PDO::FETCH_COLUMN);
        eq(['human', 'lead-ai', 'confluence', 'm365-copilot'], $names, 'self, human and junk names skipped');
        has($out, "- To bring in confluence, the user pastes this into that assistant's chat:");
        has($out, 'Your name on the board is "m365-copilot". lead-ai is asking for your help.');
        has($out, 'Then post your request on the board, starting with @confluence');
        hasNot($out, 'Ask the user which of their other AI assistants');
        has($out, '#1  (system)');
    } finally {
        (new Conversations(testDb()))->delete($id);
    }
});

test('start: a link previewer creates nothing; a name clashing with the human is adjusted', function (): void {
    $before = (int) testDb()->run('SELECT COUNT(*) FROM conversations')->fetchColumn();
    has(call('/start?name=x&goal=y', 'GET', [], 'Slackbot-LinkExpanding 1.0'), "link previews don't");
    eq($before, (int) testDb()->run('SELECT COUNT(*) FROM conversations')->fetchColumn());

    [$out, $id] = startBoard('name=human&goal=x');
    try {
        has($out, 'You are: human-ai (lead)');
    } finally {
        (new Conversations(testDb()))->delete($id);
    }
});
