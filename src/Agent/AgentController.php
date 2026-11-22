<?php

declare(strict_types=1);

namespace Cans\Agent;

use Cans\Config;
use Cans\Db;
use Cans\Domain\Activity;
use Cans\Domain\BoardNames;
use Cans\Domain\Conversations;
use Cans\Domain\HeldMessages;
use Cans\Domain\Keys;
use Cans\Domain\LimitReached;
use Cans\Domain\Messages;
use Cans\Domain\Participants;
use Cans\Http\Request;
use Cans\Http\Responses;

/**
 * The agent API under /a/{agent-key} (docs/architecture/http-api.md §2). Every response is HTTP 200
 * plain text ending with the next links (ADR-0003); every request leaves an activity row.
 */
final class AgentController
{
    /** Link previewers/unfurlers: write links answer them without writing (security-and-limits.md §3). */
    private const PREVIEWERS = '/Slackbot|SkypeUriPreview|MicrosoftPreview|facebookexternalhit|Twitterbot|LinkedInBot|Discordbot|bingpreview|WhatsApp|TelegramBot/i';

    private readonly Conversations $conversations;
    private readonly Participants $participants;
    private readonly Messages $messages;
    private readonly Activity $activity;
    private readonly HeldMessages $held;

    public function __construct(private readonly Config $config, Db $db)
    {
        $this->conversations = new Conversations($db);
        $this->participants = new Participants($db, $config);
        $this->messages = new Messages($db, $config);
        $this->activity = new Activity($db);
        $this->held = new HeldMessages($db, $this->messages);
    }

    /** GET /ai (and GET / for clients that don't ask for HTML): the service, explained to an AI. */
    public function intro(Request $request): void
    {
        Responses::agent($request, Texts::serviceIntro($this->config->baseUrl()));
    }

    /**
     * GET /start?name=…&about=…&title=…&invite=a,b&goal=… — an AI starts a board and becomes its lead
     * (ADR-0009). The response is the lead's first briefing, preceded by what to tell the user —
     * including the watch link, which no other agent response ever contains.
     */
    public function startBoard(Request $request): void
    {
        if (preg_match(self::PREVIEWERS, $request->userAgent) === 1) {
            Responses::agent($request, Texts::previewIgnored());

            return;
        }
        $ipHash = hash('sha256', $request->ip . $this->config->get('secret'));
        if ($this->conversations->createdRecentlyBy($ipHash) >= (int) $this->config->get('max_creates_per_ip_hour')) {
            Responses::agent($request, Texts::startRateLimited());

            return;
        }

        $tail = ['goal'];
        $humanName = Participants::normaliseName((string) $this->config->get('human_name')) ?? 'human';
        $name = Participants::normaliseName($request->paramBefore($tail, 'name')) ?? 'lead';
        if ($name === $humanName) {
            $name = substr($name, 0, 27) . '-ai';
        }
        $title = trim($request->paramBefore($tail, 'title'));
        $goal = $request->tail($tail);

        $this->conversations->purgeExpired();
        $conversation = $this->conversations->create($title === '' ? BoardNames::random() : $title, $goal, 24, $ipHash);
        $this->participants->add($conversation['id'], $humanName, 'human', null, null);
        $lead = $this->participants->add($conversation['id'], $name, 'lead', $request->paramBefore($tail, 'about'), null);

        $invited = [];
        foreach (explode(',', $request->paramBefore($tail, 'invite')) as $raw) {
            $helperName = Participants::normaliseName($raw);
            if ($helperName === null || in_array($helperName, [$name, $humanName], true) || isset($invited[$helperName]) || count($invited) >= 3) {
                continue;
            }
            $helper = $this->participants->add($conversation['id'], $helperName, 'helper', null, (int) $lead['id']);
            $this->messages->post($conversation['id'], null, Texts::inviteSystemMessage($name, $helperName, null), 'system', []);
            $invited[$helperName] = ['name' => $helperName, 'prompt' => Texts::starterPrompt($this->config->baseUrl(), $helper, $goal, $name)];
        }

        $me = $this->participants->findByAgentKey((string) $lead['agent_key']);
        $board = new Board($me, $this->participants->inConversation($conversation['id']), $this->config->baseUrl());
        $prefix = Texts::boardStarted($board, $this->config->baseUrl() . '/w/' . $conversation['watch_key'], array_values($invited));

        $this->handle($request, (string) $lead['agent_key'], '', $prefix);
    }

    /** @param string $prefix text put before the response (used by /start) */
    public function handle(Request $request, string $key, string $sub = '', string $prefix = ''): void
    {
        $me = Keys::isKey($key) ? $this->participants->findByAgentKey($key) : null;
        if ($me === null) {
            Responses::agent($request, Texts::unknownKey());

            return;
        }

        $action = $sub === '' ? 'start' : $sub;
        $outcome = 'error';
        try {
            if ((int) $me['expired'] === 1) {
                $outcome = 'expired';
                Responses::agent($request, Texts::expired((string) $me['expires_at']));

                return;
            }
            if ($me['removed_at'] !== null) {
                $outcome = 'removed';
                Responses::agent($request, Texts::removed());

                return;
            }

            $board = new Board($me, $this->participants->inConversation((int) $me['conversation_id']), $this->config->baseUrl());
            $paused = $board->me['paused_at'] !== null;
            if ($paused && in_array($sub, ['post', 'invite'], true) && preg_match(self::PREVIEWERS, $request->userAgent) !== 1) {
                $body = Texts::shortHeader($board) . Texts::pausedRefused($sub === 'invite') . Texts::footer($board, (int) $board->me['last_read_seq']);
                $outcome = 'refused: board paused';
            } else {
                [$body, $outcome] = match ($sub) {
                    '' => $this->start($board),
                    'read' => $this->read($board, $request),
                    'post' => $this->post($board, $request),
                    'invite' => $this->invite($board, $request),
                    'help' => [$this->help($board), 'help'],
                    default => [Texts::unknownLink($sub) . $this->help($board), 'unknown link'],
                };
            }
            if (!in_array($action, ['start', 'read', 'post', 'invite', 'help'], true)) {
                $action = 'unknown';
            }
            if ($prefix !== '') {
                $outcome = 'started the board';
            }
            Responses::agent($request, ($paused ? Texts::paused() : '') . $prefix . $body);
        } finally {
            $this->activity->log((int) $me['conversation_id'], (int) $me['id'], $action, $outcome, $request);
        }
    }

    /** GET /a/{key}: briefing (full on first visit), who's here, the latest messages. */
    private function start(Board $b): array
    {
        $firstVisit = $b->me['first_seen_at'] === null;
        $page = $this->messages->latest($b->conversationId());
        $shown = $this->lastSeq($page['messages']);
        $after = max($shown, (int) $b->me['last_read_seq']);

        $body = Texts::header($b, (int) $b->me['next_seq'] - 1)
            . Texts::heldNews($this->held->newsForPoster($b->myId()))
            . Texts::howTo($b, $after)
            . ($firstVisit ? Texts::howItWorks() . Texts::role($b) . Texts::rules() : Texts::fullInstructionsPointer($b))
            . Texts::onTheBoard($b);

        if ($page['messages'] === []) {
            $body .= Texts::noMessagesYet();
        } else {
            $body .= ($page['earlier'] ? Texts::earlierMessages($b) : '') . $this->format($b, $page['messages']);
        }
        $body .= Texts::waitingOn($this->waitingOn($b)) . "\n" . Texts::footer($b, $after);

        $this->participants->markRead($b->myId(), $shown);

        return [$body, $firstVisit ? 'first visit, briefed' : 'shown up to #' . $shown];
    }

    /** GET /a/{key}/read?after=n */
    private function read(Board $b, Request $request): array
    {
        $raw = $request->param('after', $request->param('since', $request->param('from')));
        $after = ctype_digit($raw) ? (int) $raw : (int) $b->me['last_read_seq'];
        $page = $this->messages->after($b->conversationId(), $after);

        $news = Texts::heldNews($this->held->newsForPoster($b->myId()));

        if ($page['messages'] === []) {
            $this->participants->markRead($b->myId(), 0);

            return [Texts::shortHeader($b) . $news . Texts::nothingNew($after, $this->waitingOn($b)) . Texts::footer($b, $after), "nothing after #{$after}"];
        }

        $last = $this->lastSeq($page['messages']);
        $body = Texts::shortHeader($b)
            . $news
            . $this->format($b, $page['messages'])
            . ($page['more'] ? Texts::moreMessages($b, $last) : '')
            . Texts::waitingOn($this->waitingOn($b)) . "\n"
            . Texts::footer($b, $last);
        $this->participants->markRead($b->myId(), $last);
        $first = (int) $page['messages'][0]['seq'];

        return [$body, $first === $last ? "read #{$last}" : "read #{$first}–#{$last}"];
    }

    /** GET|POST /a/{key}/post?message=… */
    private function post(Board $b, Request $request): array
    {
        if (preg_match(self::PREVIEWERS, $request->userAgent) === 1) {
            return [Texts::previewIgnored(), 'ignored link preview'];
        }

        $text = $request->message() ?? '';
        if ($text === '') {
            return [Texts::emptyMessage($b) . Texts::footer($b, (int) $b->me['last_read_seq']), 'ERROR: empty message'];
        }
        $limit = (int) $this->config->get('max_message_chars');
        $chars = mb_strlen($text);
        if ($chars > $limit) {
            return [Texts::tooLong($chars, $limit) . Texts::footer($b, (int) $b->me['last_read_seq']), "ERROR: too long ({$chars})"];
        }

        if ((int) $b->me['hold_agent_posts'] === 1) {
            $held = $this->held->hold($b->conversationId(), $b->myId(), $text, $request->method === 'POST' ? 'post' : 'get');
            $this->participants->markRead($b->myId(), 0);

            return [
                Texts::shortHeader($b) . ($held['duplicate'] ? Texts::alreadyHeld() : Texts::held($text))
                    . Texts::footer($b, (int) $b->me['last_read_seq']),
                $held['duplicate'] ? 'already held' : "held ({$chars} chars)",
            ];
        }

        try {
            $result = $this->messages->post($b->conversationId(), $b->myId(), $text, $request->method === 'POST' ? 'post' : 'get', $b->activeNames());
        } catch (LimitReached) {
            return [Texts::boardFull((int) $this->config->get('max_messages')) . Texts::footer($b, (int) $b->me['last_read_seq']), 'ERROR: board full'];
        }

        $seq = $result['seq'];
        $body = Texts::shortHeader($b) . ($result['duplicate'] ? Texts::alreadyPosted($seq) : Texts::posted($seq, $text))
            . Texts::heldNews($this->held->newsForPoster($b->myId()));

        // Anything others posted since this agent last read — saves it a round trip.
        $since = $this->messages->after($b->conversationId(), (int) $b->me['last_read_seq']);
        $myId = $b->myId();
        $others = array_values(array_filter(
            $since['messages'],
            static fn (array $m): bool => $m['participant_id'] === null || (int) $m['participant_id'] !== $myId,
        ));
        if ($others !== []) {
            $body .= Texts::meanwhile() . $this->format($b, $others);
            if ($since['more']) {
                $body .= Texts::moreMessages($b, $this->lastSeq($since['messages']));
            }
        }

        $addressees = $result['to'] === null
            ? array_map(static fn (array $p): string => (string) $p['name'], $b->otherAgents())
            : array_values(array_filter(explode(',', $result['to']), static fn (string $n): bool => $n !== $b->myName()));
        $body .= Texts::relay($addressees);

        // Read up to what was shown; if the page was cut short, no further — the rest is still unread.
        $shown = $this->lastSeq($since['messages']);
        $cursor = $since['more'] ? $shown : max($seq, $shown);
        $this->participants->markRead($b->myId(), $cursor);

        return [$body . Texts::footer($b, $cursor), $result['duplicate'] ? "duplicate of #{$seq}" : "posted #{$seq} ({$chars} chars)"];
    }

    /** GET /a/{key}/invite?name=…&about=… */
    private function invite(Board $b, Request $request): array
    {
        $footer = Texts::footer($b, (int) $b->me['last_read_seq']);
        if (preg_match(self::PREVIEWERS, $request->userAgent) === 1) {
            return [Texts::previewIgnored(), 'ignored link preview'];
        }

        $name = Participants::normaliseName($request->paramBefore(['about'], 'name'));
        if ($name === null) {
            return [Texts::badName() . $footer, 'ERROR: bad invite name'];
        }
        $about = $request->tail(['about']);
        $existing = $b->find($name);

        if ($existing !== null && $existing['removed_at'] !== null) {
            return [Texts::inviteRemoved($name) . $footer, "ERROR: {$name} was removed"];
        }
        if ($existing !== null) {
            $prompt = $existing['role'] === 'human'
                ? null
                : Texts::starterPrompt($b->base, $existing, $b->me['goal'], $b->myName());

            return $prompt === null
                ? [Texts::badName() . $footer, 'ERROR: invite named the human']
                : [Texts::alreadyInvited($name, $prompt) . $footer, "already had {$name}"];
        }

        try {
            $added = $this->participants->add($b->conversationId(), $name, 'helper', $about, $b->myId());
        } catch (LimitReached) {
            return [Texts::tooManyParticipants((int) $this->config->get('max_participants')) . $footer, 'ERROR: participants full'];
        }
        $this->messages->post($b->conversationId(), null, Texts::inviteSystemMessage($b->myName(), $name, $added['about']), 'system', []);

        $prompt = Texts::starterPrompt($b->base, $added, $b->me['goal'], $b->myName());

        return [Texts::invited($name, $added['about'], $prompt) . $footer, "invited {$name}"];
    }

    private function help(Board $b): string
    {
        $after = (int) $b->me['last_read_seq'];
        $this->participants->markRead($b->myId(), 0);

        return Texts::header($b, (int) $b->me['next_seq'] - 1)
            . Texts::howTo($b, $after)
            . Texts::howItWorks()
            . Texts::role($b)
            . Texts::rules()
            . Texts::linksReference($b)
            . Texts::onTheBoard($b)
            . Texts::footer($b, $after);
    }

    /**
     * Who the board is waiting on: the user first when the board is paused or messages await approval
     * (term-a, #18), then agents (not the human, not me) with something from someone else unread.
     */
    private function waitingOn(Board $b): string
    {
        if ($b->me['paused_at'] !== null) {
            return 'the user — the board is paused';
        }
        $waiting = [];
        $held = $this->held->countWaiting($b->conversationId());
        if ($held > 0) {
            $waiting[] = 'the user (' . ($held === 1 ? '1 message' : "{$held} messages") . ' to approve)';
        }
        foreach ($b->participants as $p) {
            if ($p['role'] === 'human') {
                $asked = $this->messages->waitingOnHuman($b->conversationId(), (int) $p['id'], (string) $p['name']);
                if ($asked !== []) {
                    $waiting[] = 'the user (to answer #' . implode(', #', array_column($asked, 'seq')) . ')';
                }
            }
        }
        foreach ($b->otherAgents() as $p) {
            if ($p['first_seen_at'] === null) {
                $waiting[] = "{$p['name']} (hasn't opened the board yet)";
                continue;
            }
            $latest = $this->messages->latestFromOthers($b->conversationId(), (int) $p['id']);
            if ((int) $p['last_read_seq'] < $latest) {
                $waiting[] = "{$p['name']} (hasn't read #{$latest})";
            }
        }

        return $waiting === [] ? 'nobody — everyone has read everything' : Texts::andList($waiting);
    }

    /** @param list<array<string, mixed>> $messages */
    private function format(Board $b, array $messages): string
    {
        return TranscriptFormatter::format($messages, $b->humanName(), gmdate('Y-m-d'));
    }

    /** @param list<array<string, mixed>> $messages */
    private function lastSeq(array $messages): int
    {
        return $messages === [] ? 0 : (int) $messages[count($messages) - 1]['seq'];
    }
}
