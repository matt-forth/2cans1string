<?php

declare(strict_types=1);

namespace Cans\Probe;

use Cans\Config;
use Cans\Db;
use Cans\Domain\Keys;
use Cans\Http\Request;
use Cans\Http\Responses;
use Throwable;

/**
 * Slice 0: finds out what a given AI tool can do with our URLs (docs/design/agent-capabilities.md).
 * Texts are from docs/design/agent-texts.md §9.
 */
final class ProbeController
{
    /** What step 2 asks the agent to send; compared to what actually arrived. */
    public const STEP2_MESSAGE = "Line one & \"quotes\" #hash\nLine two: Zoë – café ✓";

    public function __construct(private readonly Config $config, private readonly Db $db)
    {
    }

    public function instructions(Request $request): void
    {
        $tool = self::toolName($request->param('tool'));
        $echo = $this->echoUrl($tool);

        Responses::agent($request, <<<TEXT
            2 CANS 1 STRING — capability test for "{$tool}"
            The user is testing whether you can use a simple web message board. Please do these four
            steps in order, then tell the user exactly what happened at each step (including anything
            you weren't able or allowed to do):

            1. Fetch {$echo}&step=1&message=Hello%20from%20{$tool}

            2. Fetch the same link with step=2 and this two-line message, URL-encoded by you:
               Line one & "quotes" #hash
               Line two: Zoë – café ✓

            3. Fetch the same link with step=3 and a message of about 1,500 characters that you
               write yourself (any text).

            4. Fetch the "next" link that the step-3 response gives you.

            Each response tells you what the board received, so you can report it.

            TEXT);
    }

    public function echo(Request $request): void
    {
        $tool = self::toolName($request->param('tool'));
        $step = substr(preg_replace('/[^a-z0-9-]/', '', strtolower($request->param('step'))) ?? '', 0, 10);
        $message = $request->message();
        $logged = $this->record($request, $tool, $step, $message);

        $text = $message ?? '';
        $chars = mb_strlen($text);
        $lines = $text === '' ? 0 : substr_count($text, "\n") + 1;
        $nextStep = ctype_digit($step) ? (string) ((int) $step + 1) : 'next';
        $next = $this->echoUrl($tool) . "&step={$nextStep}&r=" . Keys::token() . '&message=followed-the-next-link';

        $body = "2 CANS 1 STRING — probe echo for \"{$tool}\", step " . ($step === '' ? '(none)' : $step) . "\n"
            . "Received by {$request->method}: " . ($message === null ? 'NO message parameter' : "{$chars} characters, {$lines} line(s)")
            . ', query string ' . strlen($request->rawQuery) . " bytes.\n";

        if ($message !== null) {
            $body .= "--- message as received ---\n{$text}\n--- end of message ---\n";
            $body .= 'Contains: "&" ' . self::yesNo(str_contains($text, '&'))
                . ', "#" ' . self::yesNo(str_contains($text, '#'))
                . ', non-ASCII ' . self::yesNo(preg_match('/[^\x00-\x7F]/', $text) === 1) . ".\n";
        }
        if ($step === '2') {
            $body .= self::step2Verdict($text);
        }
        $body .= "(A \"#\" that isn't encoded as %23 never reaches the server — the message is cut there.)\n";
        if (!$logged) {
            $body .= "NOTE: the server could not record this visit. Tell the user.\n";
        }
        // Step 4 is the last one: offering a step-5 link only invites a question (seen in the VS Code probe).
        $body .= $step === '4'
            ? "\nThat was the last step. Please report back to the user now.\n"
            : "\nNext: fetch {$next}\n";

        Responses::agent($request, $body);
    }

    public function log(Request $request): void
    {
        if (!hash_equals((string) $this->config->get('probe_key'), $request->param('k'))) {
            Responses::text("Not found.\n", 404);

            return;
        }

        $rows = $this->db->run(
            'SELECT at, tool, step, method, user_agent, query_length, message FROM probe_hits ORDER BY id DESC LIMIT 300'
        )->fetchAll();

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Probe log</title>'
            . '<link rel="stylesheet" href="' . e($this->config->baseUrl()) . '/assets/app.css"></head><body>'
            . '<h1>Probe log</h1><p>Newest first, last 300 hits, times UTC. Hits older than 7 days are deleted.</p>'
            . '<table><thead><tr><th>Time</th><th>Tool</th><th>Step</th><th>Method</th><th>Query bytes</th><th>Message</th><th>User agent</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . e((string) $row['at']) . '</td><td>' . e((string) $row['tool']) . '</td><td>' . e((string) $row['step'])
                . '</td><td>' . e((string) $row['method']) . '</td><td>' . e((string) $row['query_length'])
                . '</td><td class="msg">' . e((string) $row['message']) . '</td><td class="ua">' . e((string) $row['user_agent']) . '</td></tr>';
        }
        $html .= '</tbody></table></body></html>';

        Responses::html($html);
    }

    private function record(Request $request, string $tool, string $step, ?string $message): bool
    {
        try {
            $this->db->run(
                'INSERT INTO probe_hits (at, tool, step, method, path, user_agent, ip_hash, query_length, message)
                 VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $tool,
                    $step,
                    $request->method,
                    substr($request->path, 0, 200),
                    $request->userAgent,
                    hash('sha256', $request->ip . $this->config->get('secret')),
                    strlen($request->rawQuery),
                    $message === null ? null : mb_substr($message, 0, 20000),
                ],
            );
            if (random_int(1, 50) === 1) {
                $this->db->run('DELETE FROM probe_hits WHERE at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
            }

            return true;
        } catch (Throwable $e) {
            error_log('[2cans1string] probe record failed: ' . $e->getMessage());

            return false;
        }
    }

    private static function step2Verdict(string $received): string
    {
        $normalise = static fn (string $s): string => trim((string) preg_replace('/[ \t]+/', ' ', $s));
        if ($normalise($received) === $normalise(self::STEP2_MESSAGE)) {
            return "Step 2 check: arrived EXACTLY as intended.\n";
        }
        $problems = [];
        if (!str_contains($received, '#hash')) {
            $problems[] = '"#hash" missing (was "#" left unencoded? it must be %23)';
        }
        if (!str_contains($received, '& "quotes"')) {
            $problems[] = '"& \"quotes\"" missing or damaged';
        }
        if (!str_contains($received, "\n")) {
            $problems[] = 'line break missing (encode it as %0A)';
        }
        if (!str_contains($received, 'Zoë – café ✓')) {
            $problems[] = 'non-ASCII text "Zoë – café ✓" missing or damaged';
        }

        return 'Step 2 check: did NOT arrive exactly as intended' . ($problems ? ' — ' . implode('; ', $problems) : '') . ".\n";
    }

    private function echoUrl(string $tool): string
    {
        return $this->config->baseUrl() . '/probe/echo?tool=' . rawurlencode($tool);
    }

    private static function toolName(string $raw): string
    {
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($raw)), '-');

        return $name === '' ? 'unnamed-tool' : substr($name, 0, 40);
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
