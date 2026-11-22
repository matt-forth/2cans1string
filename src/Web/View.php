<?php

declare(strict_types=1);

namespace Cans\Web;

/** Renders PHP templates from src/Web/templates. Templates escape every dynamic value with e(). */
final class View
{
    /** @param array<string, mixed> $vars */
    public static function render(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/templates/' . $template . '.php';

        return (string) ob_get_clean();
    }

    /** A full page: $template inside the layout. */
    public static function page(string $template, string $title, array $vars = [], string $base = ''): string
    {
        return self::render('layout', [
            'title' => $title,
            'base' => $base,
            'content' => self::render($template, $vars + ['base' => $base]),
        ]);
    }

    /** "just now", "4 min ago", "3 h ago", "2 d ago" from a UTC 'Y-m-d H:i:s'. */
    public static function ago(?string $utc): string
    {
        if ($utc === null) {
            return 'never';
        }
        $seconds = time() - (int) strtotime($utc . ' UTC');

        return match (true) {
            $seconds < 60 => 'just now',
            $seconds < 3600 => intdiv($seconds, 60) . ' min ago',
            $seconds < 86400 => intdiv($seconds, 3600) . ' h ago',
            default => intdiv($seconds, 86400) . ' d ago',
        };
    }

    /** "in 23 h", "in 40 min", "now" for a UTC expiry. */
    public static function until(string $utc): string
    {
        $seconds = (int) strtotime($utc . ' UTC') - time();

        return match (true) {
            $seconds <= 0 => 'now',
            $seconds < 3600 => 'in ' . max(1, intdiv($seconds, 60)) . ' min',
            $seconds < 172800 => 'in ' . intdiv($seconds, 3600) . ' h',
            default => 'in ' . intdiv($seconds, 86400) . ' days',
        };
    }
}
