<?php

declare(strict_types=1);

// B1: the watch page as a conversation view for business viewers (helpers in WebFlowTest.php).

use Cans\Web\View;

test('view: initials and a stable colour per participant; the human uses the accent', function (): void {
    eq('TA', View::initials('term-a'));
    eq('VC', View::initials('vs-copilot'));
    eq('CO', View::initials('confluence'));
    $palette = View::palette([
        ['id' => 1, 'name' => 'human', 'role' => 'human'],
        ['id' => 2, 'name' => 'term-a', 'role' => 'lead'],
        ['id' => 3, 'name' => 'term-b', 'role' => 'helper'],
    ]);
    eq(['c' => 'h', 'ini' => 'You'], $palette[1]);
    eq(['c' => '0', 'ini' => 'TA'], $palette[2]);
    eq(['c' => '1', 'ini' => 'TB'], $palette[3]);
});

test('web: bubbles with from → to, turn strip, plain English, technical details closed (B1)', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        call($watch . '/add', 'POST', ['name' => 'jira', 'about' => 'Jira', 'role' => 'helper']);
        $helper = '/a/' . testDb()->run("SELECT agent_key FROM participants WHERE conversation_id = ? AND name = 'jira'", [$id])->fetchColumn();
        call($lead);
        call($lead . '/post?message=@jira%20What%20is%20open%3F');

        $page = call($watch);
        has($page, '<article class="msg theirs pc0" id="m1">');
        has($page, '<span class="avatar" aria-hidden="true">VC</span>');
        has($page, '<span class="to">→ jira</span>');
        has($page, 'Waiting on jira to join — copy its starter message into it.');
        has($page, '<summary>Technical details');
        hasNot($page, '<details class="card technical" data-keep-open="activity" open');

        call($helper);
        call($lead . '/post?message=@jira%20And%20one%20more');
        has(call($watch), 'Waiting on jira — ask it to check the board.');
        call($helper . '/read?after=1');
        call($helper . '/post?message=Two%20bugs');
        $human = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'human'", [$id])->fetchColumn();
        call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => 'Thanks both']);
        $page = call($watch);
        has($page, '<article class="msg mine pch"');
        has($page, '<span class="who">You</span>');
        has($page, '<span class="to">→ everyone</span>');

        // Outside Technical details, no protocol jargon reaches the business viewer.
        $visible = preg_replace('#<details class="card technical".*?</details>#s', '', $page);
        $visible = preg_replace('#<(pre|button|details)[^>]*>.*?</\1>#s', '', $visible); // starter messages carry the agent link by design
        foreach (['GET', 'curl', 'http://', '/a/', 'read up to', 'relay'] as $jargon) {
            hasNot(strip_tags($visible), $jargon);
        }

        $json = json_decode(call($watch . '/updates?after=0'), true);
        has($json['turnHtml'], 'turn-');
        has($json['messagesHtml'], 'class="avatar"');
    });
});

test('web: posting as you is the main action; pasting for an assistant is folded away (B4)', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        $page = call($watch);
        has($page, 'Message the assistants, as you</label>');
        has($page, '<button type="submit" class="primary big">Send</button>');
        has($page, '<details class="paste-for" >');
        has($page, 'you (normal message)');

        $human = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'human'", [$id])->fetchColumn();
        $agent = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'lead'", [$id])->fetchColumn();
        call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => 'Hello team']);
        has(call($watch), '✓ board owner</span>');

        // A failed paste-in for an assistant keeps the fold open, with its name still chosen.
        $failed = call($watch . '/post', 'POST', ['as' => (string) $agent, 'body' => '']);
        has($failed, 'Type a message first.');
        has($failed, '<details class="paste-for" open>');
    });
});

test('web: home page is plain English outside the folded AI section (B5)', function (): void {
    $home = call('/');
    $visible = preg_replace('#<details class="card for-ai".*?</details>#s', '', $home);
    $visible = preg_replace('#<(head|script)[^>]*>.*?</\1>#s', '', $visible);
    foreach (['GET', 'http://', 'https://', 'URL', '/start', 'fetch', 'query'] as $jargon) {
        hasNot(strip_tags($visible), $jargon);
    }
    has($home, '<summary>For AI assistants, and the quickest way to start</summary>');
    has($home, '/start?name=YOUR-NAME&amp;about='); // still readable by an AI fetching the HTML
});

test('web: demo polish — You avatar, long messages clamp, composer stays compact (term-a #21)', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        call($lead . '/post?message=' . rawurlencode(str_repeat("line\n", 9)));
        call($lead . '/post?message=short');
        $human = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'human'", [$id])->fetchColumn();
        call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => 'Hi']);
        $page = call($watch);
        has($page, '<span class="avatar" aria-hidden="true">You</span>');
        eq(1, substr_count($page, 'class="body clamp"'), 'only the long message is clamped');
        has($page, 'data-show-more>Show more</button>');
        has($page, 'class="post-box " id="post" data-composer>');
        has(call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => '']), 'class="post-box " id="post"');
    });
});

test('web: the expiry choice preselects the current expiry, so Update never shortens (term-a #21)', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        has(call($watch), '<option value="24" selected>');
        call($watch . '/extend', 'POST', ['hours' => '168']);
        has(call($watch), '<option value="168" selected>');
        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() + INTERVAL 30 MINUTE WHERE id = ?', [$id]);
        has(call($watch), '<option value="1" selected>');
    });
});

test('web: a message to @human makes it your turn until you post (term-a #26)', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        call($lead);
        call($lead . '/post?message=@human%20Should%20review%20be%20on%3F');
        has(call($watch), 'Waiting on you: vs-copilot asked you something.');
        has(call($lead . '/read?after=1'), 'Waiting on: the user (to answer #1).');

        $human = (int) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'human'", [$id])->fetchColumn();
        call($watch . '/post', 'POST', ['as' => (string) $human, 'body' => 'Yes']);
        hasNot(call($watch), 'asked you something');
        hasNot(call($lead . '/read?after=2'), 'to answer');
    });
});
