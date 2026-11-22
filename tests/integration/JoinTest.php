<?php

declare(strict_types=1);

// QR codes and the /j join page (ADR-0011). Helpers in AgentFlowTest.php / WebFlowTest.php.

test('join: /j shows one assistant its starter message, plain English, and is not a visit (ADR-0011)', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        $key = substr($lead, 3);
        $page = call('/j/' . $key);
        has($page, 'Add <strong>vs-copilot</strong> to “Web test');
        has($page, 'Copy the message</button>');
        has($page, 'Please fetch ' . testConfig()->baseUrl() . $lead . ' now');
        has($page, 'anyone with it can join as vs-copilot');

        $visible = preg_replace('#<(head|pre|button)[^>]*>.*?</\1>#s', '', $page);
        foreach (['GET', 'http://', '/a/', 'fetch', 'URL'] as $jargon) {
            hasNot(strip_tags($visible), $jargon);
        }
        eq(null, testDb()->run("SELECT first_seen_at FROM participants WHERE agent_key = ?", [$key])->fetchColumn(), 'viewing the join page is not the agent visiting');
    });
});

test('join: an unknown, removed or expired key says only "not valid"', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        has(call('/j/' . str_repeat('a', 20)), "This join link isn't valid any more");
        has(call('/j/nonsense'), "This join link isn't valid any more");

        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?', [$id]);
        $expired = call('/j/' . substr($lead, 3));
        has($expired, "This join link isn't valid any more");
        hasNot($expired, 'vs-copilot');
        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() + INTERVAL 1 HOUR WHERE id = ?', [$id]);

        $agentId = (string) testDb()->run("SELECT id FROM participants WHERE conversation_id = ? AND role = 'lead'", [$id])->fetchColumn();
        call($watch . '/remove', 'POST', ['id' => $agentId]);
        has(call('/j/' . substr($lead, 3)), "This join link isn't valid any more");
    });
});

test('watch: Show QR code beside each assistant and on Step 1, panel hidden, library local', function (): void {
    withWebBoard(function (int $id, string $watch, string $lead): void {
        $join = testConfig()->baseUrl() . '/j/' . substr($lead, 3);
        $page = call($watch);
        eq(2, substr_count($page, 'data-qr="' . $join . '"'), 'Step 1 card and the assistant card');
        has($page, '<div class="qr-overlay" data-qr-panel hidden');
        has($page, 'Anyone who scans this can post as');
        has($page, '<script src="' . testConfig()->baseUrl() . '/assets/vendor/qrcode-generator/qrcode.js" defer></script>');
        ok(is_file(dirname(__DIR__, 2) . '/public/assets/vendor/qrcode-generator/qrcode.js'), 'library is vendored');
        ok(is_file(dirname(__DIR__, 2) . '/public/assets/vendor/qrcode-generator/LICENSE'), 'licence shipped with it');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');
        hasNot($js, 'innerHTML = svg');
        ok(preg_match('#https?://[^\'"\s]*(qr|chart)#i', $js) === 0, 'no online QR service');
    });
});
