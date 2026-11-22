<?php

declare(strict_types=1);

use Cans\Agent\Texts;
use Cans\Agent\TranscriptFormatter;
use Cans\Domain\Messages;
use Cans\Domain\Participants;

function msg(int $seq, ?string $author, string $body, array $extra = []): array
{
    return $extra + [
        'seq' => $seq, 'participant_id' => $author === null ? null : 1, 'body' => $body, 'to_names' => null,
        'via' => 'get', 'created_at' => '2026-11-22 14:03:00', 'author_name' => $author,
        'author_role' => 'helper', 'author_removed_at' => null,
    ];
}

test('transcript format: header line, body, blank line; arrows for addressees', function (): void {
    $out = TranscriptFormatter::format([
        msg(5, 'vs-copilot', "Please find\nthe rules", ['to_names' => 'confluence,m365']),
        msg(6, 'matt', 'Also PAY-1234.', ['author_role' => 'human', 'created_at' => '2026-11-21 09:00:00']),
    ], 'matt', '2026-11-22');
    eq("#5  vs-copilot → confluence, m365   14:03 UTC\nPlease find\nthe rules\n\n"
        . "#6  matt (human)   2026-11-21 09:00 UTC\nAlso PAY-1234.\n\n", $out);
});

test('transcript format: system, relayed and removed authors', function (): void {
    $out = TranscriptFormatter::format([
        msg(1, null, 'x added y.', ['via' => 'system']),
        msg(2, 'rovo', 'hi', ['via' => 'relay']),
        msg(3, 'gone', 'bye', ['author_removed_at' => '2026-11-22 15:00:00']),
    ], 'matt', '2026-11-22');
    ok(str_contains($out, "#1  (system)   14:03 UTC"), 'system');
    ok(str_contains($out, "#2  rovo (via matt)   14:03 UTC"), 'relay');
    ok(str_contains($out, "#3  gone (removed)   14:03 UTC"), 'removed');
});

test('mentions: leading @names that exist, de-duplicated; @all means everyone', function (): void {
    $known = ['confluence', 'm365', 'vs-copilot'];
    eq('confluence', Messages::mentions('@confluence please look', $known));
    eq('confluence,m365', Messages::mentions('@Confluence, @m365: both of you @confluence', $known));
    eq(null, Messages::mentions('@all hello', $known));
    eq(null, Messages::mentions('hello @confluence', $known), 'only leading mentions count');
    eq(null, Messages::mentions('@nobody hello', $known));
    eq('m365', Messages::mentions('@m365 @nobody hi', $known), 'stops at first unknown');
});

test('duplicate hash ignores case and whitespace', function (): void {
    eq(Messages::hash("Hello   World\n"), Messages::hash('hello world'));
    ok(Messages::hash('hello world') !== Messages::hash('hello world!'), 'different text differs');
});

test('names normalise to [a-z0-9-], 2–30 chars', function (): void {
    eq('vs-copilot', Participants::normaliseName(' VS Copilot '));
    eq('m365-copilot', Participants::normaliseName('m365_copilot'));
    eq('rovo', Participants::normaliseName('Rovo!!'));
    eq(null, Participants::normaliseName('x'));
    eq(null, Participants::normaliseName('!!!'));
    eq(null, Participants::normaliseName(str_repeat('a', 31)));
});

test('andList reads naturally', function (): void {
    eq('a', Texts::andList(['a']));
    eq('a and b', Texts::andList(['a', 'b']));
    eq('a, b and c', Texts::andList(['a', 'b', 'c']));
});
