<?php

declare(strict_types=1);

namespace Cans\Http;

/** Sends responses with the headers every response carries (http-api.md §1.5). */
final class Responses
{
    public static function text(string $body, int $status = 200): void
    {
        self::send($body, 'text/plain; charset=utf-8', $status);
    }

    /** Agent-facing text; ?format=html wraps the identical text in <pre> for HTML-only fetchers. */
    public static function agent(Request $request, string $body): void
    {
        if (strtolower($request->param('format')) === 'html') {
            self::html(
                '<!doctype html><html><head><meta charset="utf-8"><title>2 cans 1 string</title></head>'
                . '<body><pre>' . e($body) . '</pre></body></html>'
            );

            return;
        }
        self::text($body);
    }

    public static function html(string $body, int $status = 200): void
    {
        if (!headers_sent()) {
            header("Content-Security-Policy: default-src 'self'");
        }
        self::send($body, 'text/html; charset=utf-8', $status);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data): void
    {
        self::send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json; charset=utf-8', 200);
    }

    /** 303 See Other: after a form POST, the browser GETs the target. */
    public static function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
        }
        self::send('', 'text/plain; charset=utf-8', 303);
    }

    private static function send(string $body, string $contentType, int $status): void
    {
        // Already sent only when driven in-process by tests/run.php; a web request never gets here
        // with output pending.
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType);
            header('Cache-Control: no-store');
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: no-referrer');
            header('X-Content-Type-Options: nosniff');
        }
        echo $body;
    }
}
