<?php

declare(strict_types=1);

// public/.htaccess can't run under the test runner (no Apache), so check its rule order instead.

test('htaccess: /.well-known/ is exempt before the HTTPS redirect and the dotfile block', function (): void {
    $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
    $wellKnown = strpos($rules, 'RewriteRule ^\.well-known/ - [L]');
    $https = strpos($rules, 'RewriteRule ^ https://');
    $dotfiles = strpos($rules, 'RewriteRule (^|/)\. - [F,L]');
    ok($wellKnown !== false && $https !== false && $dotfiles !== false, 'all three rules present');
    ok($wellKnown < $https && $wellKnown < $dotfiles, 'the .well-known exemption comes first, or certificate renewals get 403/301');
});
