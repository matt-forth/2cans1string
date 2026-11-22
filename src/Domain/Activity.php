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

    /** @return list<array<string, mixed>> newest first, with the participant's name */
    public function recent(int $conversationId, int $limit = 50): array
    {
        return $this->db->run(
            'SELECT a.at, a.action, a.outcome, a.method, a.user_agent, a.query_length, p.name
             FROM activity a LEFT JOIN participants p ON p.id = a.participant_id
             WHERE a.conversation_id = ? ORDER BY a.id DESC LIMIT ' . $limit,
            [$conversationId],
        )->fetchAll();
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
