<?php

declare(strict_types=1);

// Join codes (ADR-0012): a short code only asks to join; the owner lets people in.

use Cans\Domain\JoinCodes;

/** The board's current join code, raw (e.g. "K7M4QX"). */
function currentCode(int $id): string
{
    return (string) testDb()->run('SELECT join_code FROM conversations WHERE id = ?', [$id])->fetchColumn();
}

test('join code: format, display, and forgiving input', function (): void {
    for ($i = 0; $i < 50; $i++) {
        $code = JoinCodes::generate();
        ok(preg_match('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{6}$/', $code) === 1, "code {$code} uses the safe alphabet");
    }
    eq('K7M 4QX', JoinCodes::display('K7M4QX'));
    eq('K7M4QX', JoinCodes::normalise(' k7m-4qx '));
    eq('K7M4QX', JoinCodes::normalise('K7M 4QX'));
    eq(null, JoinCodes::normalise('K7M4Q0'), '0 is not in the alphabet');
    eq(null, JoinCodes::normalise('K7M4Q'), 'too short');
});

test('join code: get, show, stop; a wrong or expired code reveals nothing', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        has(call($watch), 'Get a join code</button>');
        call($watch . '/code', 'POST', ['do' => 'start']);
        $code = currentCode($id);
        has(call($watch), '<p class="join-code" aria-label="Join code">' . JoinCodes::display($code) . '</p>');

        $wrong = call('/join', 'POST', ['code' => 'ZZZ ZZZ', 'name' => 'phone']);
        has($wrong, "That code isn&#039;t valid. Check it, or ask the board&#039;s owner for a new one.");
        hasNot($wrong, 'Web test');

        testDb()->run('UPDATE conversations SET join_code_expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?', [$id]);
        has(call('/join', 'POST', ['code' => $code, 'name' => 'phone']), "That code isn&#039;t valid.");
        has(call($watch), 'Get a join code</button>');

        call($watch . '/code', 'POST', ['do' => 'start']);
        $code = currentCode($id);
        call($watch . '/code', 'POST', ['do' => 'stop']);
        has(call('/join', 'POST', ['code' => $code, 'name' => 'phone']), "That code isn&#039;t valid.");
        eq(0, (int) testDb()->run('SELECT COUNT(*) FROM join_requests WHERE conversation_id = ?', [$id])->fetchColumn(), 'no request filed');
    });
});

test('join code: ask, wait, owner lets in, waiting page becomes the join page', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        call($watch . '/code', 'POST', ['do' => 'start']);
        $code = strtolower(JoinCodes::display(currentCode($id)));
        has(call('/join', 'POST', ['code' => $code, 'name' => 'vs-copilot']), 'That name is already used on this board.');

        call('/join', 'POST', ['code' => $code, 'name' => 'ChatGPT Phone', 'about' => 'my inbox']);
        $token = (string) testDb()->run('SELECT token FROM join_requests WHERE conversation_id = ?', [$id])->fetchColumn();
        $wait = call('/join/' . $token);
        has($wait, "Waiting for the board's owner to let you in");
        has($wait, 'data-join-wait');
        has(call('/join', 'POST', ['code' => $code, 'name' => 'chatgpt-phone']), 'That name is already used', );

        $page = call($watch);
        has($page, 'Asking to join <span class="count">1</span>');
        has($page, 'Waiting on you: chatgpt-phone wants to join.');
        eq(0, (int) testDb()->run("SELECT COUNT(*) FROM participants WHERE conversation_id = ? AND name = 'chatgpt-phone'", [$id])->fetchColumn(), 'not on the board yet');

        $requestId = (string) testDb()->run('SELECT id FROM join_requests WHERE token = ?', [$token])->fetchColumn();
        call($watch . '/let-in', 'POST', ['id' => $requestId]);
        call($watch . '/let-in', 'POST', ['id' => $requestId]); // a double click adds nobody twice
        $agent = testDb()->run("SELECT agent_key, role, about FROM participants WHERE conversation_id = ? AND name = 'chatgpt-phone'", [$id])->fetchAll();
        eq(1, count($agent));
        eq(['helper', 'my inbox'], [$agent[0]['role'], $agent[0]['about']]);

        // Once let in, /join/{token} redirects to the /j join page (a header, so not visible here).
        hasNot(call('/join/' . $token), 'Waiting for the board');
        has(call('/j/' . $agent[0]['agent_key']), 'Add <strong>chatgpt-phone</strong>');
        hasNot(call($watch), 'Asking to join');
    });
});

test('join code: decline tells the requester; bad tokens say only "not valid"; the pages are plain', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        call($watch . '/code', 'POST', ['do' => 'start']);
        call('/join', 'POST', ['code' => currentCode($id), 'name' => 'phone']);
        $row = testDb()->run('SELECT id, token FROM join_requests WHERE conversation_id = ?', [$id])->fetch();
        call($watch . '/turn-away', 'POST', ['id' => (string) $row['id']]);
        $declined = call('/join/' . $row['token']);
        has($declined, "The board's owner said no");
        hasNot($declined, 'data-join-wait');
        eq(0, (int) testDb()->run("SELECT COUNT(*) FROM participants WHERE conversation_id = ? AND name = 'phone'", [$id])->fetchColumn());

        has(call('/join/' . str_repeat('a', 20)), "This join link isn't valid any more");
        foreach ([call('/'), $declined] as $page) {
            $visible = preg_replace('#<details class="card for-ai".*?</details>#s', '', $page);
            $visible = preg_replace('#<(head|script)[^>]*>.*?</\1>#s', '', $visible);
            foreach (['GET', 'http://', 'URL', 'fetch', 'token'] as $jargon) {
                hasNot(strip_tags($visible), $jargon);
            }
        }
    });
});

test('join code: at most 10 waiting requests per board', function (): void {
    withWebBoard(function (int $id, string $watch): void {
        call($watch . '/code', 'POST', ['do' => 'start']);
        for ($i = 1; $i <= JoinCodes::MAX_WAITING; $i++) {
            call('/join', 'POST', ['code' => currentCode($id), 'name' => "phone-{$i}"]);
        }
        has(call('/join', 'POST', ['code' => currentCode($id), 'name' => 'one-more']), 'Too many requests are waiting on this board.');
    });
});
