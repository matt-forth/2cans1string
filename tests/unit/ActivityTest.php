<?php

declare(strict_types=1);

use Cans\Domain\Activity;

function activityRow(int $who, string $at, string $action, string $outcome): array
{
    return ['participant_id' => $who, 'at' => $at, 'action' => $action, 'outcome' => $outcome];
}

test('activity: consecutive identical empty reads by one agent collapse into one row (A4)', function (): void {
    $rows = [ // newest first
        activityRow(2, '09:00:30', 'read', 'nothing after #2'),
        activityRow(2, '09:00:20', 'read', 'nothing after #2'),
        activityRow(2, '09:00:10', 'read', 'nothing after #2'),
        activityRow(2, '09:00:05', 'post', 'posted #2 (5 chars)'),
        activityRow(2, '09:00:04', 'read', 'nothing after #1'),
        activityRow(3, '09:00:03', 'read', 'nothing after #1'),
        activityRow(3, '09:00:02', 'read', 'read #1'),
        activityRow(3, '09:00:01', 'read', 'read #1'),
    ];
    $out = Activity::collapse($rows);
    eq(6, count($out));
    eq(3, $out[0]['count']);
    eq('09:00:10', $out[0]['first_at']);
    eq('09:00:30', $out[0]['at']);
    eq('post', $out[1]['action']);
    eq(1, $out[2]['count'], 'different agent breaks the run');
    eq(1, $out[3]['count']);
    eq([1, 1], [$out[4]['count'], $out[5]['count']], 'reads that found something never merge');
});

test('activity: two agents polling in turn still collapse per agent; a post closes the runs (A4)', function (): void {
    $rows = [ // newest first
        activityRow(2, '09:00:40', 'read', 'nothing after #5'),
        activityRow(3, '09:00:35', 'read', 'nothing after #5'),
        activityRow(2, '09:00:30', 'read', 'nothing after #5'),
        activityRow(3, '09:00:25', 'read', 'nothing after #5'),
        activityRow(2, '09:00:20', 'post', 'posted #5 (3 chars)'),
        activityRow(3, '09:00:15', 'read', 'nothing after #5'),
    ];
    $out = Activity::collapse($rows);
    eq(4, count($out));
    eq([2, 2], [$out[0]['count'], $out[1]['count']]);
    eq('09:00:30', $out[0]['first_at']);
    eq('09:00:25', $out[1]['first_at']);
    eq('post', $out[2]['action']);
    eq(1, $out[3]['count'], 'a read before the post stays separate');
});
