<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Db;

final class Conversations
{
    public function __construct(private readonly Db $db)
    {
    }

    /** @return array{id: int, watch_key: string} */
    public function create(string $title, ?string $goal, int $expiryHours, string $creatorIpHash, bool $holdAgentPosts = false): array
    {
        $watchKey = Keys::key();
        $this->db->run(
            'INSERT INTO conversations (watch_key, title, goal, created_at, last_activity_at, expires_at, creator_ip_hash, hold_agent_posts)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? HOUR, ?, ?)',
            [$watchKey, mb_substr(trim($title), 0, 120), $goal === null || trim($goal) === '' ? null : trim($goal), $expiryHours, $creatorIpHash, $holdAgentPosts ? 1 : 0],
        );

        return ['id' => (int) $this->db->pdo()->lastInsertId(), 'watch_key' => $watchKey];
    }

    /** @return array<string, mixed>|null the conversation, with `expired` (0/1) */
    public function findByWatchKey(string $watchKey): ?array
    {
        $row = $this->db->run(
            'SELECT c.*, (c.expires_at < UTC_TIMESTAMP()) AS expired FROM conversations c WHERE c.watch_key = ?',
            [$watchKey],
        )->fetch();

        return $row === false ? null : $row;
    }

    /** Sets expiry to $hours from now (ADR-0008: never beyond 7 days from now). */
    public function extend(int $id, int $hours): void
    {
        $this->db->run(
            'UPDATE conversations SET expires_at = UTC_TIMESTAMP() + INTERVAL ? HOUR WHERE id = ?',
            [min($hours, 168), $id],
        );
    }

    /** Pause (ADR-0010): while set, agents are told to wait and their posts are refused. */
    public function setPaused(int $id, bool $paused): void
    {
        $this->db->run('UPDATE conversations SET paused_at = ' . ($paused ? 'COALESCE(paused_at, UTC_TIMESTAMP())' : 'NULL') . ' WHERE id = ?', [$id]);
    }

    /** Hold before send (ADR-0010): agent posts wait for the human's approval. */
    public function setHold(int $id, bool $hold): void
    {
        $this->db->run('UPDATE conversations SET hold_agent_posts = ? WHERE id = ?', [$hold ? 1 : 0, $id]);
    }

    public function createdRecentlyBy(string $creatorIpHash): int
    {
        return (int) $this->db->run(
            'SELECT COUNT(*) FROM conversations WHERE creator_ip_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR',
            [$creatorIpHash],
        )->fetchColumn();
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM conversations WHERE id = ?', [$id]);
    }

    /**
     * Hard-deletes boards an hour past expiry, with everything in them (ADR-0008); the hour lets agents
     * see "expired". Runs on board creation and on ~2% of requests, so no cron is needed.
     *
     * @return int boards deleted
     */
    public function purgeExpired(): int
    {
        return $this->db->run('DELETE FROM conversations WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR')->rowCount();
    }
}
