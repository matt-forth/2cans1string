<?php

declare(strict_types=1);

// public/robots.txt: only the public pages are crawlable (security-and-limits.md §3).
// Google/Bing semantics: the longest matching rule wins, Allow wins a tie; `$` anchors the end.

function robotsAllows(string $robots, string $path): bool
{
    $best = ['len' => -1, 'allow' => true];
    foreach (preg_split('/\R/', $robots) ?: [] as $line) {
        if (!preg_match('/^(Allow|Disallow):\s*(\S+)/i', trim($line), $m)) {
            continue;
        }
        $rule = $m[2];
        $regex = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '#')) . '#';
        if (preg_match($regex, $path)) {
            $len = strlen($rule);
            $allow = strtolower($m[1]) === 'allow';
            if ($len > $best['len'] || ($len === $best['len'] && $allow)) {
                $best = ['len' => $len, 'allow' => $allow];
            }
        }
    }

    return $best['allow'];
}

test('robots.txt: the public pages are crawlable, nothing with a key or a side effect is', function (): void {
    $robots = (string) file_get_contents(dirname(__DIR__, 2) . '/public/robots.txt');
    foreach (['/', '/how', '/ai', '/assets/app.css'] as $open) {
        ok(robotsAllows($robots, $open), "crawlable: {$open}");
    }
    foreach (['/a/abc', '/a/abc/post?message=hi', '/w/abc', '/j/abc', '/join', '/join/abc', '/start?name=x',
        '/new', '/probe', '/probe/echo?message=x', '/?deleted=1', '/how-else', '/aim'] as $closed) {
        ok(!robotsAllows($robots, $closed), "blocked: {$closed}");
    }
});
