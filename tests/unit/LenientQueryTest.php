<?php

declare(strict_types=1);

use Cans\Http\LenientQuery;

test('message is everything after message=, unencoded & included', function (): void {
    $q = LenientQuery::parse('tool=vs&message=fish & chips & peas');
    eq('fish & chips & peas', $q->message);
    eq(['tool' => 'vs'], $q->params);
});

test('plus and %20 both decode to spaces', function (): void {
    eq('a b c', LenientQuery::parse('message=a+b%20c')->message);
});

test('encoded newlines, & and # survive', function (): void {
    eq("one & two\n#three", LenientQuery::parse('message=one%20%26%20two%0A%23three')->message);
});

test('CRLF normalised to LF and outer whitespace trimmed', function (): void {
    eq("a\nb", LenientQuery::parse('message=%20%20a%0D%0Ab%0A')->message);
});

test('non-ASCII UTF-8 decodes', function (): void {
    eq('Zoë – café ✓', LenientQuery::parse('message=' . rawurlencode('Zoë – café ✓'))->message);
});

test('double-encoded message is decoded twice', function (): void {
    $once = rawurlencode('hello there, world & friends');
    eq('hello there, world & friends', LenientQuery::parse('message=' . rawurlencode($once))->message);
});

test('single-encoded text with literal % sequences is not over-decoded when it has spaces', function (): void {
    eq('rate is 50%25 of %41 and %42 and %43 ok', LenientQuery::parse('message=rate+is+50%2525+of+%2541+and+%2542+and+%2543+ok')->message);
});

test('malformed percent sequences are left alone', function (): void {
    eq('100% sure', LenientQuery::parse('message=100%%20sure')->message);
});

test('invalid UTF-8 is scrubbed, not fatal', function (): void {
    $m = LenientQuery::parse('message=ok%FF%FEok')->message;
    ok($m !== null && preg_match('//u', $m) === 1, 'result is valid UTF-8');
    ok(str_starts_with($m, 'ok') && str_ends_with($m, 'ok'), 'surrounding text kept');
});

test('aliases msg, m and text work, case-insensitively', function (): void {
    eq('x', LenientQuery::parse('msg=x')->message);
    eq('y', LenientQuery::parse('M=y')->message);
    eq('z', LenientQuery::parse('Text=z')->message);
});

test('names merely ending in an alias do not count', function (): void {
    $q = LenientQuery::parse('context=abc&item=def');
    eq(null, $q->message);
    eq(['context' => 'abc', 'item' => 'def'], $q->params);
});

test('first message parameter wins; later ones are part of the text', function (): void {
    eq('a&message=b', LenientQuery::parse('message=a&message=b')->message);
});

test('trailing r= and format= copied onto the end are stripped', function (): void {
    eq('hello', LenientQuery::parse('message=hello&r=abc123')->message);
    eq('hello', LenientQuery::parse('message=hello&format=html&r=x')->message);
});

test('empty and missing message', function (): void {
    eq('', LenientQuery::parse('message=')->message);
    eq(null, LenientQuery::parse('tool=x')->message);
    eq(null, LenientQuery::parse('')->message);
});

test('params are lower-cased, decoded, and the first occurrence wins', function (): void {
    eq(['tool' => 'vs code', 'step' => '1'], LenientQuery::parse('Tool=vs+code&step=1&tool=other')->params);
});

test('clean() tidies POST fields the same way', function (): void {
    eq("a\nb", LenientQuery::clean("  a\r\nb  "));
});
