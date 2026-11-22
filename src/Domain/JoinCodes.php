<?php

declare(strict_types=1);

namespace Cans\Domain;

use Cans\Db;

/**
 * Short join codes (ADR-0012), like a boarding-pass reference: "K7M 4QX". A code never grants access.
 * Redeeming one only files a request, which the board's owner approves or declines. So a guessed
 * code is worth nothing, even without rate limits.
 */
final class JoinCodes
{
    /** No 0/O, 1/I/L: easy to read off a screen and say out loud. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const LENGTH = 6;
    public const VALID_MINUTES = 15;
    public const MAX_WAITING = 10;

    public function __construct(private readonly Db $db, private readonly Participants $participants)
    {
    }

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** "k7m-4qx " -> "K7M4QX"; null if it can't be a code. */
    public static function normalise(string $typed): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-.]+/', '', $typed));

        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    /** "K7M4QX" -> "K7M 4QX". */
    public static function display(string $code): string
    {
        return substr($code, 0, 3) . ' ' . substr($code, 3);
    }

    /** A fresh code for the board, replacing any current one; valid for 15 minutes. */
    public function start(int $conversationId): string
    {
        for ($try = 0; $try < 5; $try++) {
            $code = self::generate();
            try {
                $this->db->run(
                    'UPDATE conversations SET join_code = ?, join_code_expires_at = UTC_TIMESTAMP() + INTERVAL ? MINUTE WHERE id = ?',
                    [$code, self::VALID_MINUTES, $conversationId],
                );

                return $code;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') { // only a clash with another board's code is retried
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Could not find a free join code');
    }

    public function stop(int $conversationId): void
    {
        $this->db->run('UPDATE conversations SET join_code = NULL, join_code_expires_at = NULL WHERE id = ?', [$conversationId]);
    }

    /**
     * Files a request to join the board behind a code. Every failure that could reveal something
     * about a board (wrong, expired or stopped code; expired board) is the same 'invalid'.
     *
     * @return array{ok: bool, error: string, token: string} error: invalid | name | taken | full
     */
    public function request(string $typedCode, string $rawName, string $about): array
    {
        $fail = static fn (string $error): array => ['ok' => false, 'error' => $error, 'token' => ''];
        $code = self::normalise($typedCode);
        if ($code === null) {
            return $fail('invalid');
        }
        $board = $this->db->run(
            'SELECT id FROM conversations
             WHERE join_code = ? AND join_code_expires_at > UTC_TIMESTAMP() AND expires_at > UTC_TIMESTAMP()',
            [$code],
        )->fetch();
        if ($board === false) {
            return $fail('invalid');
        }
        $id = (int) $board['id'];
        $name = Participants::normaliseName($rawName);
        if ($name === null) {
            return $fail('name');
        }
        $takenOnBoard = array_map(static fn (array $p): string => (string) $p['name'], $this->participants->inConversation($id));
        $waiting = $this->waiting($id);
        if (in_array($name, $takenOnBoard, true) || in_array($name, array_column($waiting, 'name'), true)) {
            return $fail('taken');
        }
        if (count($waiting) >= self::MAX_WAITING) {
            return $fail('full');
        }
        $about = trim($about);
        $token = Keys::key();
        $this->db->run(
            'INSERT INTO join_requests (conversation_id, token, name, about, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, $token, $name, $about === '' ? null : mb_substr($about, 0, 300)],
        );

        return ['ok' => true, 'error' => '', 'token' => $token];
    }

    /** @return array<string, mixed>|null the request (with its board's title and the new agent's key once approved) */
    public function findRequest(string $token): ?array
    {
        if (!Keys::isKey($token)) {
            return null;
        }
        $row = $this->db->run(
            'SELECT r.*, c.title, p.agent_key, (c.expires_at < UTC_TIMESTAMP()) AS expired
             FROM join_requests r JOIN conversations c ON c.id = r.conversation_id
             LEFT JOIN participants p ON p.id = r.participant_id
             WHERE r.token = ?',
            [$token],
        )->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> requests waiting for the owner, oldest first */
    public function waiting(int $conversationId): array
    {
        return $this->db->run(
            "SELECT id, name, about, created_at FROM join_requests WHERE conversation_id = ? AND status = 'waiting' ORDER BY id",
            [$conversationId],
        )->fetchAll();
    }

    /**
     * Lets a requester in as a helper. Throws LimitReached when the board is full of participants.
     *
     * @return string|null the new participant's name; null if not waiting, or the name was taken meanwhile
     */
    public function approve(int $conversationId, int $requestId): ?string
    {
        $row = $this->db->run(
            "SELECT name, about FROM join_requests WHERE id = ? AND conversation_id = ? AND status = 'waiting'",
            [$requestId, $conversationId],
        )->fetch();
        if ($row === false) {
            return null;
        }
        $taken = array_map(static fn (array $p): string => (string) $p['name'], $this->participants->inConversation($conversationId));
        if (in_array($row['name'], $taken, true)) {
            $this->decline($conversationId, $requestId);

            return null;
        }
        // Claim it first, so a double-clicked Approve can't add the assistant twice.
        $claimed = $this->db->run(
            "UPDATE join_requests SET status = 'approved', decided_at = UTC_TIMESTAMP() WHERE id = ? AND status = 'waiting'",
            [$requestId],
        )->rowCount() === 1;
        if (!$claimed) {
            return null;
        }
        try {
            $added = $this->participants->add($conversationId, (string) $row['name'], 'helper', $row['about'], null);
        } catch (LimitReached $e) {
            $this->db->run("UPDATE join_requests SET status = 'waiting', decided_at = NULL WHERE id = ?", [$requestId]);
            throw $e;
        }
        $this->db->run('UPDATE join_requests SET participant_id = ? WHERE id = ?', [(int) $added['id'], $requestId]);

        return (string) $added['name'];
    }

    public function decline(int $conversationId, int $requestId): bool
    {
        return $this->db->run(
            "UPDATE join_requests SET status = 'declined', decided_at = UTC_TIMESTAMP() WHERE id = ? AND conversation_id = ? AND status = 'waiting'",
            [$requestId, $conversationId],
        )->rowCount() === 1;
    }
}
