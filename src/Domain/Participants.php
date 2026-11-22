<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Config;
use Cans\Db;

final class Participants
{
    public const ROLES = ['lead', 'helper', 'human'];

    public function __construct(private readonly Db $db, private readonly Config $config)
    {
    }

    /** "VS Copilot" -> "vs-copilot". Null when nothing usable (2–30 chars of [a-z0-9-]) remains. */
    public static function normaliseName(string $raw): ?string
    {
        $name = strtolower(trim($raw));
        $name = (string) preg_replace('/[\s_.]+/', '-', $name);
        $name = (string) preg_replace('/[^a-z0-9-]/', '', $name);
        $name = trim((string) preg_replace('/-+/', '-', $name), '-');
        $length = strlen($name);

        return $length >= 2 && $length <= 30 ? $name : null;
    }

    /**
     * Adds a participant. Agents get a key; the human doesn't. Throws LimitReached when full.
     *
     * @return array<string, mixed> the new row
     */
    public function add(int $conversationId, string $name, string $role, ?string $about, ?int $invitedBy): array
    {
        if ($this->countActive($conversationId) >= (int) $this->config->get('max_participants')) {
            throw new LimitReached('participants');
        }
        $about = $about === null ? null : trim($about);
        if ($about !== null && mb_strlen($about) > 300) {
            $about = mb_substr($about, 0, 299) . '…';
        }
        $this->db->run(
            'INSERT INTO participants (conversation_id, agent_key, name, role, about, invited_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$conversationId, $role === 'human' ? null : Keys::key(), $name, $role, $about === '' ? null : $about, $invitedBy],
        );

        return $this->db->run('SELECT * FROM participants WHERE id = ?', [(int) $this->db->pdo()->lastInsertId()])->fetch();
    }

    /**
     * The participant behind an agent key, with its conversation's fields. Never selects the watch key.
     *
     * @return array<string, mixed>|null
     */
    public function findByAgentKey(string $agentKey): ?array
    {
        $row = $this->db->run(
            'SELECT p.*, c.title, c.goal, c.next_seq, c.expires_at, (c.expires_at < UTC_TIMESTAMP()) AS expired
             FROM participants p JOIN conversations c ON c.id = p.conversation_id
             WHERE p.agent_key = ?',
            [$agentKey],
        )->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> everyone, removed included, in joining order */
    public function inConversation(int $conversationId): array
    {
        return $this->db->run('SELECT * FROM participants WHERE conversation_id = ? ORDER BY id', [$conversationId])->fetchAll();
    }

    public function countActive(int $conversationId): int
    {
        return (int) $this->db->run(
            'SELECT COUNT(*) FROM participants WHERE conversation_id = ? AND removed_at IS NULL',
            [$conversationId],
        )->fetchColumn();
    }

    /** Revokes an agent's link; its messages stay, marked "(removed)". The human can't be removed. */
    public function remove(int $conversationId, int $participantId): void
    {
        $this->db->run(
            "UPDATE participants SET removed_at = UTC_TIMESTAMP()
             WHERE id = ? AND conversation_id = ? AND role <> 'human' AND removed_at IS NULL",
            [$participantId, $conversationId],
        );
    }

    /** Records a visit and advances the read cursor (never backwards). */
    public function markRead(int $participantId, int $seq): void
    {
        $this->db->run(
            'UPDATE participants
             SET last_read_seq = GREATEST(last_read_seq, ?), last_seen_at = UTC_TIMESTAMP(),
                 first_seen_at = COALESCE(first_seen_at, UTC_TIMESTAMP())
             WHERE id = ?',
            [$seq, $participantId],
        );
    }
}
