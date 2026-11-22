<?php

declare(strict_types=1);

namespace Cans\Http;

final class Request
{
    /** @param array<string, string> $post */
    public function __construct(
        public readonly string $method,
        /** Path relative to the site's base path, lower-cased, no trailing slash ('/' for the root). */
        public readonly string $path,
        /** The raw, undecoded query string — the lenient parser needs it untouched. */
        public readonly string $rawQuery,
        public readonly array $post,
        public readonly string $userAgent,
        public readonly string $ip,
        /** The Accept header. Browsers always include text/html; many agent HTTP clients don't. */
        public readonly string $accept = 'text/html',
    ) {
    }

    public function wantsHtml(): bool
    {
        return str_contains(strtolower($this->accept), 'text/html');
    }

    public static function fromGlobals(string $basePath): self
    {
        $uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rawurldecode($uriPath);
        if ($basePath !== '' && str_starts_with(strtolower($path), strtolower($basePath))) {
            $path = substr($path, strlen($basePath));
        }
        $path = '/' . trim(strtolower($path), '/');

        $post = [];
        foreach ($_POST as $name => $value) {
            if (is_string($value)) {
                $post[strtolower((string) $name)] = $value;
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            (string) ($_SERVER['QUERY_STRING'] ?? ''),
            $post,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_ACCEPT'] ?? ''),
        );
    }

    /** Query parameters before the message (or all of them, when there is no message). */
    public function params(): array
    {
        return LenientQuery::parse($this->rawQuery)->params;
    }

    public function param(string $name, string $default = ''): string
    {
        $value = $this->params()[$name] ?? ($this->post[$name] ?? null);

        return $value === null ? $default : $value;
    }

    /** The message: from a POST form field if present, otherwise leniently from the raw query. */
    public function message(): ?string
    {
        return $this->tail(LenientQuery::MESSAGE_NAMES);
    }

    /**
     * A parameter that takes the rest of the query string (see LenientQuery), or its POST field.
     *
     * @param list<string> $names
     */
    public function tail(array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($this->post[$name])) {
                return LenientQuery::clean($this->post[$name]);
            }
        }

        return LenientQuery::parse($this->rawQuery, $names)->message;
    }

    /** @param list<string> $tailNames */
    public function paramBefore(array $tailNames, string $name): string
    {
        return LenientQuery::parse($this->rawQuery, $tailNames)->params[$name] ?? ($this->post[$name] ?? '');
    }
}
