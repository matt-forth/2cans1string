<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Config;
use Cans\Db;
use Throwable;

final class Messages
{
    private const SELECT = 'SELECT m.seq, m.participant_id, m.body, m.to_names, m.via, m.created_at,
                                   p.name AS author_name, p.role AS author_role, p.removed_at AS author_removed_at
                            FROM messages m LEFT JOIN participants p ON p.id = m.participant_id';

    public function __construct(private readonly Db $db, private readonly Config $config)
    {
    }

    /**
     * Posts a message (data-model.md: seq allocation under a row lock, duplicate suppression).
     * Throws LimitReached when the board is full.
     *
     * @param list<string> $knownNames participant names that @mentions may address
     * @return array{seq: int, duplicate: bool, to: ?string}
     */
    public function post(int $conversationId, ?int $participantId, string $body, string $via, array $knownNames): array
    {
        $hash = self::hash($body);
        $to = self::mentions($body, $knownNames);
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $nextSeq = (int) $this->db->run('SELECT next_seq FROM conversations WHERE id = ? FOR UPDATE', [$conversationId])->fetchColumn();

            if ($participantId !== null) {
                $duplicate = $this->db->run(
                    'SELECT seq FROM messages
                     WHERE conversation_id = ? AND participant_id = ? AND body_hash = ?
                       AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
                     ORDER BY seq DESC LIMIT 1',
                    [$conversationId, $participantId, $hash, (int) $this->config->get('dedupe_minutes')],
                )->fetchColumn();
                if ($duplicate !== false) {
                    $pdo->commit();

                    return ['seq' => (int) $duplicate, 'duplicate' => true, 'to' => $to];
                }
            }

            if ($nextSeq - 1 >= (int) $this->config->get('max_messages')) {
                throw new LimitReached('messages');
            }

            $this->db->run(
                'INSERT INTO messages (conversation_id, seq, participant_id, to_names, body, body_hash, via, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$conversationId, $nextSeq, $participantId, $to, $body, $hash, $via],
            );
            $this->db->run(
                'UPDATE conversations SET next_seq = next_seq + 1, last_activity_at = UTC_TIMESTAMP() WHERE id = ?',
                [$conversationId],
            );
            $pdo->commit();

            return ['seq' => $nextSeq, 'duplicate' => false, 'to' => $to];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Messages after a seq, oldest first, within the page budget (http-api.md §5).
     *
     * @return array{messages: list<array<string, mixed>>, more: bool}
     */
    public function after(int $conversationId, int $afterSeq): array
    {
        $limit = (int) $this->config->get('page_messages');
        $rows = $this->db->run(
            self::SELECT . ' WHERE m.conversation_id = ? AND m.seq > ? ORDER BY m.seq LIMIT ' . ($limit + 1),
            [$conversationId, $afterSeq],
        )->fetchAll();

        $page = $this->fit(array_slice($rows, 0, $limit));

        return ['messages' => $page, 'more' => count($rows) > count($page)];
    }

    /**
     * The newest page, oldest first.
     *
     * @return array{messages: list<array<string, mixed>>, earlier: bool}
     */
    public function latest(int $conversationId): array
    {
        $rows = $this->db->run(
            self::SELECT . ' WHERE m.conversation_id = ? ORDER BY m.seq DESC LIMIT ' . (int) $this->config->get('page_messages'),
            [$conversationId],
        )->fetchAll();

        $page = array_reverse($this->fit($rows));

        return ['messages' => $page, 'earlier' => $page !== [] && (int) $page[0]['seq'] > 1];
    }

    /**
     * Every message after a seq, unpaged — for the human's watch page (boards are capped at 500).
     *
     * @return list<array<string, mixed>>
     */
    public function allAfter(int $conversationId, int $afterSeq): array
    {
        return $this->db->run(self::SELECT . ' WHERE m.conversation_id = ? AND m.seq > ? ORDER BY m.seq', [$conversationId, $afterSeq])->fetchAll();
    }

    /** Highest seq written by anyone other than this participant (system messages don't count). */
    public function latestFromOthers(int $conversationId, int $participantId): int
    {
        return (int) $this->db->run(
            'SELECT COALESCE(MAX(seq), 0) FROM messages WHERE conversation_id = ? AND participant_id IS NOT NULL AND participant_id <> ?',
            [$conversationId, $participantId],
        )->fetchColumn();
    }

    /**
     * Leading @name tokens that match participants, comma-joined; null = everyone (none, or @all).
     *
     * @param list<string> $knownNames
     */
    public static function mentions(string $body, array $knownNames): ?string
    {
        $names = [];
        $rest = ltrim($body);
        while (preg_match('/^@([a-z0-9][a-z0-9-]*)[,:;]?\s*/i', $rest, $match) === 1) {
            $name = strtolower($match[1]);
            if ($name === 'all' || $name === 'everyone') {
                return null;
            }
            if (!in_array($name, $knownNames, true)) {
                break;
            }
            $names[$name] = true;
            $rest = substr($rest, strlen($match[0]));
        }

        return $names === [] ? null : implode(',', array_keys($names));
    }

    /** Same text modulo case and whitespace = same hash (ADR-0004). */
    public static function hash(string $body): string
    {
        return hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $body))));
    }

    /**
     * Keeps messages, in the given order, while they fit the character budget; always keeps the first.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function fit(array $rows): array
    {
        $budget = (int) $this->config->get('page_chars');
        $kept = [];
        $used = 0;
        foreach ($rows as $row) {
            $used += mb_strlen((string) $row['body']);
            if ($kept !== [] && $used > $budget) {
                break;
            }
            $kept[] = $row;
        }

        return $kept;
    }
}
