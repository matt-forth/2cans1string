<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Db;
use Cans\Http\Request;

/** One row per agent request: the human's answer to "did it reach us, and what arrived?" */
final class Activity
{
    private const KEEP = 500;

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * The newest rows for the watch page, with runs of identical empty reads collapsed into one.
     *
     * @return list<array<string, mixed>> newest first, with the participant's name, `count` and `first_at`
     */
    public function recent(int $conversationId, int $limit = 50): array
    {
        $rows = $this->db->run(
            'SELECT a.participant_id, a.at, a.action, a.outcome, a.method, a.user_agent, a.query_length, p.name
             FROM activity a LEFT JOIN participants p ON p.id = a.participant_id
             WHERE a.conversation_id = ? ORDER BY a.id DESC LIMIT ' . self::KEEP,
            [$conversationId],
        )->fetchAll();

        return array_slice(self::collapse($rows), 0, $limit);
    }

    /**
     * Merges repeated "nothing new" reads by the same participant (rows newest first). Two agents that
     * poll interleave, so each participant has its own open run; any other kind of visit (a post, a
     * read that found something, a start…) closes every run, so merged rows never jump over an event.
     * Without this, a polling agent pushes the visits that matter off the page in minutes.
     *
     * @param list<array<string, mixed>> $rows newest first
     * @return list<array<string, mixed>> each with `count` (rows merged) and `first_at` (oldest of them)
     */
    public static function collapse(array $rows): array
    {
        $out = [];
        $open = []; // participant id => index in $out of its current run
        foreach ($rows as $row) {
            $row['count'] = 1;
            $row['first_at'] = $row['at'];
            $empty = $row['action'] === 'read' && str_starts_with((string) $row['outcome'], 'nothing after');
            if (!$empty) {
                $open = [];
                $out[] = $row;
                continue;
            }
            $who = (string) $row['participant_id'];
            $run = $open[$who] ?? null;
            if ($run !== null && $out[$run]['outcome'] === $row['outcome']) {
                $out[$run]['count']++;
                $out[$run]['first_at'] = $row['at'];
                continue;
            }
            $out[] = $row;
            $open[$who] = count($out) - 1;
        }

        return $out;
    }

    public function log(int $conversationId, ?int $participantId, string $action, string $outcome, Request $request): void
    {
        $this->db->run(
            'INSERT INTO activity (conversation_id, participant_id, at, action, outcome, method, user_agent, query_length)
             VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?, ?)',
            [
                $conversationId,
                $participantId,
                substr($action, 0, 20),
                mb_substr($outcome, 0, 200),
                $request->method,
                $request->userAgent === '' ? null : $request->userAgent,
                strlen($request->rawQuery),
            ],
        );

        if (random_int(1, 50) === 1) {
            $cutoff = $this->db->run(
                'SELECT id FROM activity WHERE conversation_id = ? ORDER BY id DESC LIMIT 1 OFFSET ' . (self::KEEP - 1),
                [$conversationId],
            )->fetchColumn();
            if ($cutoff !== false) {
                $this->db->run('DELETE FROM activity WHERE conversation_id = ? AND id < ?', [$conversationId, (int) $cutoff]);
            }
        }
    }
}
