<?php

declare(strict_types=1);

namespace Cans\Agent;

/** Messages -> the plain-text format agents read (http-api.md §6). */
final class TranscriptFormatter
{
    /**
     * @param list<array<string, mixed>> $messages rows from Domain\Messages
     * @param string                     $today    'Y-m-d' (UTC); times on other days show the date
     */
    public static function format(array $messages, string $humanName, string $today): string
    {
        $out = '';
        foreach ($messages as $m) {
            $to = $m['to_names'] === null ? '' : ' → ' . str_replace(',', ', ', (string) $m['to_names']);
            $created = (string) $m['created_at'];
            $time = (str_starts_with($created, $today) ? substr($created, 11, 5) : substr($created, 0, 16)) . ' UTC';
            $out .= '#' . $m['seq'] . '  ' . self::who($m, $humanName) . $to . '   ' . $time . "\n" . $m['body'] . "\n\n";
        }

        return $out;
    }

    /** @param array<string, mixed> $m */
    private static function who(array $m, string $humanName): string
    {
        if ($m['author_name'] === null) {
            return '(system)';
        }
        $who = (string) $m['author_name'];
        if ($m['author_role'] === 'human') {
            $who .= ' (human)';
        } elseif ($m['via'] === 'relay') {
            $who .= " (via {$humanName})";
        }
        if ($m['author_removed_at'] !== null) {
            $who .= ' (removed)';
        }

        return $who;
    }
}
