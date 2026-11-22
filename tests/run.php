<?php

declare(strict_types=1);

// Tiny test runner (ADR-0007: no PHPUnit). Run: php tests/run.php  — exits 1 on any failure.
// Each tests/unit/*Test.php file calls test('name', fn) and uses the assert helpers below.

require dirname(__DIR__) . '/src/bootstrap.php';

/** @var array<string, callable> $tests */
$tests = [];

function test(string $name, callable $body): void
{
    $GLOBALS['tests'][$name] = $body;
}

final class AssertionFailed extends Exception
{
}

function eq(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($label !== '' ? "$label: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function ok(bool $condition, string $label): void
{
    if (!$condition) {
        throw new AssertionFailed($label);
    }
}

foreach (glob(__DIR__ . '/unit/*Test.php') ?: [] as $file) {
    require $file;
}

// Integration tests need the database in config.local.php; skipped (loudly) when it's unreachable.
// Only the connection is guarded — a broken test file must fail the run, not look like a skip.
$databaseError = null;
try {
    (new Cans\Db(Cans\Config::load(dirname(__DIR__))))->pdo();
} catch (Throwable $e) {
    $databaseError = $e->getMessage();
}
if ($databaseError === null) {
    foreach (glob(__DIR__ . '/integration/*Test.php') ?: [] as $file) {
        require $file;
    }
} else {
    echo "  SKIP  integration tests — no database: {$databaseError}\n";
}

$failed = 0;
foreach ($tests as $name => $body) {
    try {
        $body();
        echo "  pass  $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
    }
}

$total = count($tests);
echo "\n" . ($total - $failed) . " of $total passed" . ($failed ? ", $failed FAILED" : '') . "\n";
exit($failed ? 1 : 0);
