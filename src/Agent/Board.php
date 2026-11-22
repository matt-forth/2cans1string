<?php

declare(strict_types=1);

namespace Cans\Agent;

use Cans\Domain\Keys;

/** Everything a response to one agent needs to know: who's asking, on which board, with whom. */
final class Board
{
    /**
     * @param array<string, mixed>       $me           participant + conversation fields (Participants::findByAgentKey)
     * @param list<array<string, mixed>> $participants everyone on the board, removed included
     */
    public function __construct(
        public readonly array $me,
        public readonly array $participants,
        public readonly string $base,
    ) {
    }

    public function conversationId(): int
    {
        return (int) $this->me['conversation_id'];
    }

    public function myId(): int
    {
        return (int) $this->me['id'];
    }

    public function myName(): string
    {
        return (string) $this->me['name'];
    }

    public function myRole(): string
    {
        return (string) $this->me['role'];
    }

    public function link(string $sub = ''): string
    {
        return $this->base . '/a/' . $this->me['agent_key'] . ($sub === '' ? '' : '/' . $sub);
    }

    /** A read link with a fresh r= token, so no fetch tool can serve it from cache (ADR-0003). */
    public function readLink(int $after): string
    {
        return $this->link('read') . '?after=' . $after . '&r=' . Keys::token();
    }

    public function postTemplate(): string
    {
        return $this->link('post') . '?message=YOUR-MESSAGE';
    }

    /** @return list<array<string, mixed>> */
    public function active(): array
    {
        return array_values(array_filter($this->participants, static fn (array $p): bool => $p['removed_at'] === null));
    }

    /** @return list<array<string, mixed>> active agents other than me */
    public function otherAgents(): array
    {
        $myId = $this->myId();

        return array_values(array_filter(
            $this->active(),
            static fn (array $p): bool => $p['role'] !== 'human' && (int) $p['id'] !== $myId,
        ));
    }

    /** @return list<string> */
    public function activeNames(): array
    {
        return array_map(static fn (array $p): string => (string) $p['name'], $this->active());
    }

    public function find(string $name): ?array
    {
        foreach ($this->participants as $p) {
            if ($p['name'] === $name) {
                return $p;
            }
        }

        return null;
    }

    public function humanName(): string
    {
        foreach ($this->participants as $p) {
            if ($p['role'] === 'human') {
                return (string) $p['name'];
            }
        }

        return 'the user';
    }

    public function leadName(): string
    {
        foreach ($this->active() as $p) {
            if ($p['role'] === 'lead') {
                return (string) $p['name'];
            }
        }

        return 'the lead';
    }
}
