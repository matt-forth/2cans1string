<?php

declare(strict_types=1);

use Cans\Domain\Keys;

test('keys are 20 chars from the look-alike-free alphabet', function (): void {
    $key = Keys::key();
    eq(20, strlen($key));
    ok(Keys::isKey($key), "isKey($key)");
    ok(strpbrk($key, '01oli') === false, 'no look-alikes');
});

test('10,000 keys are all distinct', function (): void {
    $keys = [];
    for ($i = 0; $i < 10000; $i++) {
        $keys[Keys::key()] = true;
    }
    eq(10000, count($keys));
});

test('isKey rejects wrong length and characters', function (): void {
    ok(!Keys::isKey('abc'), 'too short');
    ok(!Keys::isKey(str_repeat('a', 19) . '0'), 'contains 0');
    ok(!Keys::isKey(str_repeat('A', 20)), 'upper case');
});

test('tokens are 6 chars', function (): void {
    eq(6, strlen(Keys::token()));
});
