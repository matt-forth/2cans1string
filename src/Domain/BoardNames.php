<?php

declare(strict_types=1);

namespace Cans\Domain;

/**
 * Default board titles: three easy-to-say words, e.g. "pickle-quest-chitchat" — meaningless but easy
 * to say out loud and pass on. A title is only a label, never a key. Words are hand-picked to be
 * cheerful and office-safe.
 */
final class BoardNames
{
    private const FIRST = [
        'amber', 'bouncy', 'breezy', 'bright', 'bubbly', 'cheery', 'clever', 'cosmic', 'cozy', 'crisp',
        'curious', 'dapper', 'dizzy', 'fizzy', 'fluffy', 'friendly', 'gentle', 'giddy', 'golden', 'happy',
        'jolly', 'lively', 'lucky', 'mellow', 'merry', 'misty', 'nimble', 'peppy', 'perky', 'plucky',
        'quirky', 'rainbow', 'rosy', 'snappy', 'sparkly', 'sunny', 'swift', 'tidy', 'uplifting', 'velvet',
        'witty', 'zesty', 'pickle', 'teal', 'tangy',
    ];

    private const SECOND = [
        'acorn', 'anchor', 'balloon', 'banjo', 'beacon', 'biscuit', 'compass', 'cookie', 'cupcake', 'discovery',
        'doodle', 'falcon', 'garden', 'harbor', 'kettle', 'kite', 'lantern', 'lemon', 'meadow', 'mango',
        'marble', 'muffin', 'noodle', 'orbit', 'otter', 'paddle', 'pebble', 'penguin', 'pepper', 'pillow',
        'quest', 'rocket', 'saddle', 'sprout', 'teapot', 'tiger', 'toffee', 'trumpet', 'waffle', 'whiteboard',
        'willow', 'yo-yo', 'zebra', 'puzzle', 'comet',
    ];

    private const THIRD = [
        'banter', 'brainstorm', 'briefing', 'catchup', 'chatter', 'chinwag', 'chitchat', 'confab', 'council',
        'debrief', 'dialogue', 'exchange', 'gabfest', 'gathering', 'huddle', 'hustle', 'ideation', 'jam',
        'kickoff', 'meetup', 'natter', 'parley', 'roundtable', 'scrum', 'session',
        'showcase', 'standup', 'summit', 'swap', 'sync', 'talk', 'teamup', 'workshop', 'yarn', 'riff',
        'chat', 'think-tank', 'jamboree',
    ];

    public static function random(): string
    {
        return self::pick(self::FIRST) . '-' . self::pick(self::SECOND) . '-' . self::pick(self::THIRD);
    }

    /** @param list<string> $words */
    private static function pick(array $words): string
    {
        return $words[random_int(0, count($words) - 1)];
    }
}
