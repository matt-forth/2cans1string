<?php

declare(strict_types=1);

namespace Cans\Http;

/**
 * Sends responses with the headers every response carries (http-api.md §1.5). Everything is
 * noindex unless the caller passes $indexable: only the public pages (/, /how, /ai) do.
 */
final class Responses
{
    public static function text(string $body, int $status = 200, bool $indexable = false): void
    {
        self::send($body, 'text/plain; charset=utf-8', $status, $indexable);
    }

    /** Agent-facing text; ?format=html wraps the identical text in <pre> for HTML-only fetchers. */
    public static function agent(Request $request, string $body, bool $indexable = false): void
    {
        if (strtolower($request->param('format')) === 'html') {
            self::html(
                '<!doctype html><html><head><meta charset="utf-8"><title>2 cans 1 string</title></head>'
                . '<body><pre>' . e($body) . '</pre></body></html>',
                200,
                $indexable
            );

            return;
        }
        self::text($body, 200, $indexable);
    }

    public static function html(string $body, int $status = 200, bool $indexable = false): void
    {
        if (!headers_sent()) {
            header("Content-Security-Policy: default-src 'self'");
        }
        self::send($body, 'text/html; charset=utf-8', $status, $indexable);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data): void
    {
        self::send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json; charset=utf-8', 200, false);
    }

    /** 303 See Other: after a form POST, the browser GETs the target. */
    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
        }
        self::send('', 'text/plain; charset=utf-8', 303, false);
    }

    private static function send(string $body, string $contentType, int $status, bool $indexable): void
    {
        // Already sent only when driven in-process by tests/run.php; a web request never gets here
        // with output pending.
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType);
            header('Cache-Control: no-store');
            if (!$indexable) {
                header('X-Robots-Tag: noindex, nofollow');
            }
            header('Referrer-Policy: no-referrer');
            header('X-Content-Type-Options: nosniff');
        }
        echo $body;
    }
}
