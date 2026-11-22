<?php

declare(strict_types=1);

namespace Cans\Http;

/**
 * Parses a raw query string the way agents actually write them (docs/architecture/http-api.md §4).
 *
 * Everything after the first message= (or alias) is the message, even unencoded '&'. Parameters
 * before it are parsed normally. Decoding forgives '+' vs '%20', double encoding and bad UTF-8.
 */
final class LenientQuery
{
    public const MESSAGE_NAMES = ['message', 'msg', 'm', 'text'];

    /** Parameters an agent may copy onto the end of a link by mistake; stripped from the message. */
    private const TRAILING_JUNK = '/&(?:r|format)=[^&]*$/i';

    private function __construct(
        /** @var array<string, string> lower-cased names => decoded values */
        public readonly array $params,
        public readonly ?string $message,
    ) {
    }

    /**
     * @param list<string> $tailNames the parameter that takes the rest of the query string — the
     *                                message by default; 'about' for invites.
     */
    public static function parse(string $rawQuery, array $tailNames = self::MESSAGE_NAMES): self
    {
        $names = implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), $tailNames));
        if (preg_match('/(?:^|&)(?:' . $names . ')=/i', $rawQuery, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return new self(self::parsePairs($rawQuery), null);
        }

        $start = $match[0][1];
        $prefix = substr($rawQuery, 0, $start);
        $rawMessage = substr($rawQuery, $start + strlen($match[0][0]));

        while (preg_match(self::TRAILING_JUNK, $rawMessage) === 1) {
            $rawMessage = (string) preg_replace(self::TRAILING_JUNK, '', $rawMessage);
        }

        return new self(self::parsePairs($prefix), self::decodeMessage($rawMessage));
    }

    /** Tidies an already-decoded message (e.g. a POST field): valid UTF-8, \n line endings, trimmed. */
    public static function clean(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", self::scrubUtf8($text)));
    }

    private static function decodeMessage(string $raw): string
    {
        $text = self::decode($raw);

        // Double-encoded: no spaces left, but still full of %XX sequences.
        if (!str_contains($text, ' ') && preg_match_all('/%[0-9A-Fa-f]{2}/', $text) >= 3) {
            $text = self::decode($text);
        }

        return self::clean($text);
    }

    /** '+' means space; rawurldecode leaves malformed '%' sequences alone. */
    private static function decode(string $raw): string
    {
        return rawurldecode(str_replace('+', ' ', $raw));
    }

    /** @return array<string, string> */
    private static function parsePairs(string $query): array
    {
        $params = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = strtolower(self::decode($name));
            if ($name !== '' && !array_key_exists($name, $params)) {
                $params[$name] = self::clean(self::decode($value));
            }
        }

        return $params;
    }

    private static function scrubUtf8(string $text): string
    {
        if (function_exists('mb_scrub')) {
            return mb_scrub($text, 'UTF-8');
        }

        return (string) iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }
}
