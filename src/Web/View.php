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

    /** A full page: $template inside the layout. Pages are noindex unless $indexable (public pages only). */
    public static function page(string $template, string $title, array $vars = [], string $base = '', bool $indexable = false): string
    {
        return self::render('layout', [
            'title' => $title,
            'base' => $base,
            'indexable' => $indexable,
            'content' => self::render($template, $vars + ['base' => $base]),
        ]);
    }

    /** Colours for agents on the watch page; the human always uses the accent ("h"). */
    public const COLOURS = 8;

    /**
     * Each participant's colour and initials, by joining order, so a name keeps its colour all session.
     *
     * @param list<array<string, mixed>> $participants everyone, removed included (Participants::inConversation)
     * @return array<int, array{c: string, ini: string}> keyed by participant id
     */
    public static function palette(array $participants): array
    {
        $out = [];
        $n = 0;
        foreach ($participants as $p) {
            $out[(int) $p['id']] = [
                'c' => $p['role'] === 'human' ? 'h' : (string) ($n++ % self::COLOURS),
                'ini' => $p['role'] === 'human' ? 'You' : self::initials((string) $p['name']),
            ];
        }

        return $out;
    }

    /** "term-a" -> "TA", "vs-copilot" -> "VC", "confluence" -> "CO". (The human's avatar says "You".) */
    public static function initials(string $name): string
    {
        $parts = array_values(array_filter(explode('-', $name), static fn (string $s): bool => $s !== ''));
        if (count($parts) >= 2) {
            return strtoupper($parts[0][0] . $parts[1][0]);
        }

        return strtoupper(substr($name, 0, 2));
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
