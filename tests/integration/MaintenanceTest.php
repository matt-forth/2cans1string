<?php

declare(strict_types=1);

// C2: expired boards are deleted without cron, with everything in them (ADR-0008).

use Cans\Domain\Conversations;

test('purge: a board an hour past expiry goes, with messages, held messages and activity', function (): void {
    withBoard(function (array $b): void {
        (new Conversations(testDb()))->setHold($b['id'], true);
        call($b['lead'] . '/post?message=held%20one');
        (new Conversations(testDb()))->setHold($b['id'], false);
        call($b['lead'] . '/post?message=posted%20one');

        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() - INTERVAL 30 MINUTE WHERE id = ?', [$b['id']]);
        (new Conversations(testDb()))->purgeExpired();
        has(call($b['lead']), 'ERROR: This board expired at'); // within the grace hour: still explains itself

        testDb()->run('UPDATE conversations SET expires_at = UTC_TIMESTAMP() - INTERVAL 2 HOUR WHERE id = ?', [$b['id']]);
        ok((new Conversations(testDb()))->purgeExpired() >= 1, 'purge reports what it deleted');
        foreach (['conversations' => 'id', 'participants' => 'conversation_id', 'messages' => 'conversation_id', 'held_messages' => 'conversation_id', 'activity' => 'conversation_id'] as $table => $column) {
            eq(0, (int) testDb()->run("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$b['id']])->fetchColumn(), "{$table} emptied");
        }
        has(call($b['lead']), "ERROR: This link isn't recognised.");
    });
});
