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
    public function create(string $title, ?string $goal, int $expiryHours, string $creatorIpHash): array
    {
        $watchKey = Keys::key();
        $this->db->run(
            'INSERT INTO conversations (watch_key, title, goal, created_at, last_activity_at, expires_at, creator_ip_hash)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ? HOUR, ?)',
            [$watchKey, mb_substr(trim($title), 0, 120), $goal === null || trim($goal) === '' ? null : trim($goal), $expiryHours, $creatorIpHash],
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

    /** Hard-deletes boards an hour past expiry (ADR-0008); the hour lets agents see "expired". */
    public function purgeExpired(): void
    {
        $this->db->run('DELETE FROM conversations WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR');
    }
}
