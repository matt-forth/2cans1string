<?php

declare(strict_types=1);

// Only the public pages drop noindex (security-and-limits.md §3). The X-Robots-Tag header can't be
// seen in-process, so this pins the meta tag; Responses sends the header on the same flag.

test('crawl: home and /how are indexable; board pages stay noindex', function (): void {
    hasNot(call('/'), 'noindex');
    hasNot(call('/how'), 'noindex');
    withWebBoard(function (int $id, string $watch, string $lead): void {
        has(call($watch), '<meta name="robots" content="noindex, nofollow">');
        has(call('/j/' . substr($lead, 3)), '<meta name="robots" content="noindex, nofollow">');
    });
});
