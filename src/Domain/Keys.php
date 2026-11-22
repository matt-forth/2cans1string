<?php

declare(strict_types=1);

namespace Cans\Domain;

/** Random keys and tokens from an alphabet without look-alikes (no 0 o 1 l i). ADR-0002. */
final class Keys
{
    public const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    public const KEY_LENGTH = 20;

    /** A capability key (watch key or agent key): ~99 bits. */
    public static function key(): string
    {
        return self::random(self::KEY_LENGTH);
    }

    /** A short token that only makes a URL unique (the r= in next links). Not a secret. */
    public static function token(): string
    {
        return self::random(6);
    }

    public static function isKey(string $candidate): bool
    {
        return strlen($candidate) === self::KEY_LENGTH
            && strspn($candidate, self::ALPHABET) === self::KEY_LENGTH;
    }

    private static function random(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
