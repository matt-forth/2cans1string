<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Db;

/**
 * Agent posts waiting for the human's approval (ADR-0010). A held message has no seq: it becomes a
 * real message, through Messages::post, only when approved — so read cursors never see a gap.
 */
final class HeldMessages
{
    public function __construct(private readonly Db $db, private readonly Messages $messages)
    {
    }

    /**
     * Holds an agent's post. The same text already waiting from the same agent is not held twice.
     *
     * @return array{id: int, duplicate: bool}
     */
    public function hold(int $conversationId, int $participantId, string $body, string $via): array
    {
        $hash = Messages::hash($body);
        $existing = $this->db->run(
            "SELECT id FROM held_messages WHERE participant_id = ? AND body_hash = ? AND status = 'held' LIMIT 1",
            [$participantId, $hash],
        )->fetchColumn();
        if ($existing !== false) {
            return ['id' => (int) $existing, 'duplicate' => true];
        }
        $this->db->run(
            'INSERT INTO held_messages (conversation_id, participant_id, body, body_hash, via, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$conversationId, $participantId, $body, $hash, $via === 'post' ? 'post' : 'get'],
        );

        return ['id' => (int) $this->db->pdo()->lastInsertId(), 'duplicate' => false];
    }

    /** @return list<array<string, mixed>> messages waiting for approval, oldest first, with author name */
    public function waiting(int $conversationId): array
    {
        return $this->db->run(
            "SELECT h.id, h.participant_id, h.body, h.created_at, p.name AS author_name
             FROM held_messages h JOIN participants p ON p.id = h.participant_id
             WHERE h.conversation_id = ? AND h.status = 'held' ORDER BY h.id",
            [$conversationId],
        )->fetchAll();
    }

    /**
     * What the poster should hear about its own held messages: everything still waiting, plus each
     * decision it hasn't been told about yet (which this marks as told).
     *
     * @return list<array<string, mixed>> oldest first: status, body, created_at, seq
     */
    public function newsForPoster(int $participantId): array
    {
        $rows = $this->db->run(
            "SELECT id, status, body, created_at, seq FROM held_messages
             WHERE participant_id = ? AND (status = 'held' OR poster_told_at IS NULL) ORDER BY id",
            [$participantId],
        )->fetchAll();
        $decided = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] !== 'held'));
        if ($decided !== []) {
            $ids = array_map(static fn (array $r): int => (int) $r['id'], $decided);
            $this->db->run(
                'UPDATE held_messages SET poster_told_at = UTC_TIMESTAMP() WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                $ids,
            );
        }

        return $rows;
    }

    /**
     * Posts a held message for real. Throws LimitReached when the board is full.
     *
     * @param list<string> $knownNames
     * @return int|null the seq it became, or null if it wasn't waiting (already decided, other board)
     */
    public function approve(int $conversationId, int $heldId, array $knownNames): ?int
    {
        // Claim it first, so a double-clicked Approve can't post it twice.
        if (!$this->decide($conversationId, $heldId, 'approved')) {
            return null;
        }
        $row = $this->db->run('SELECT participant_id, body, via FROM held_messages WHERE id = ?', [$heldId])->fetch();
        try {
            $result = $this->messages->post($conversationId, (int) $row['participant_id'], (string) $row['body'], (string) $row['via'], $knownNames);
        } catch (LimitReached $e) {
            $this->db->run("UPDATE held_messages SET status = 'held', decided_at = NULL WHERE id = ?", [$heldId]);
            throw $e;
        }
        $this->db->run('UPDATE held_messages SET seq = ? WHERE id = ?', [$result['seq'], $heldId]);

        return $result['seq'];
    }

    public function discard(int $conversationId, int $heldId): bool
    {
        return $this->decide($conversationId, $heldId, 'discarded');
    }

    /** Moves a message on this board from 'held' to $status; false if it wasn't waiting. */
    private function decide(int $conversationId, int $heldId, string $status): bool
    {
        return $this->db->run(
            "UPDATE held_messages SET status = ?, decided_at = UTC_TIMESTAMP()
             WHERE id = ? AND conversation_id = ? AND status = 'held'",
            [$status, $heldId, $conversationId],
        )->rowCount() === 1;
    }
}
