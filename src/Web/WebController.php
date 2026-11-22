<?php

declare(strict_types=1);

namespace Cans\Web;

use Cans\Agent\Texts;
use Cans\Agent\TranscriptFormatter;
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

/** The human's side: home, the guide, creating a board, the watch page (docs/design/human-pages.md). */
final class WebController
{
    private readonly Conversations $conversations;
    private readonly Participants $participants;
    private readonly Messages $messages;
    private readonly Activity $activity;
    private readonly HeldMessages $held;

    /** Posted as the human when they press Pause / Resume (ADR-0010). */
    public const PAUSE_MESSAGE = '@everyone pause, wait for the human.';
    public const RESUME_MESSAGE = '@everyone resume: carry on where you left off.';

    public function __construct(private readonly Config $config, Db $db)
    {
        $this->conversations = new Conversations($db);
        $this->participants = new Participants($db, $config);
        $this->messages = new Messages($db, $config);
        $this->activity = new Activity($db);
        $this->held = new HeldMessages($db, $this->messages);
    }

    public function home(Request $request, array $form = [], string $error = ''): void
    {
        Responses::html(View::page('home', '2 cans 1 string', [
            'form' => $form + ['title' => BoardNames::random(), 'goal' => '', 'lead' => 'lead', 'about' => '', 'hours' => '24', 'hold' => false],
            'error' => $error,
            'expiryChoices' => $this->config->get('expiry_choices_hours'),
            'deleted' => $request->param('deleted') === '1',
        ], $this->config->baseUrl()));
    }

    public function how(Request $request): void
    {
        Responses::html(View::page('how', 'How it works — 2 cans 1 string', [], $this->config->baseUrl()));
    }

    /** POST /new */
    public function create(Request $request): void
    {
        $form = [
            'title' => trim($request->post['title'] ?? ''),
            'goal' => trim($request->post['goal'] ?? ''),
            'lead' => $request->post['lead'] ?? 'lead',
            'about' => trim($request->post['about'] ?? ''),
            'hours' => $request->post['hours'] ?? '24',
            'hold' => ($request->post['hold'] ?? '') === 'on',
        ];
        $hours = in_array((int) $form['hours'], $this->config->get('expiry_choices_hours'), true) ? (int) $form['hours'] : 24;
        $leadName = Participants::normaliseName($form['lead']);
        $ipHash = hash('sha256', $request->ip . $this->config->get('secret'));

        $error = match (true) {
            $form['title'] === '' => 'Give the board a title.',
            $leadName === null => 'The first assistant needs a short name: letters, digits and dashes, e.g. vs-copilot.',
            $leadName === Participants::normaliseName((string) $this->config->get('human_name')) => 'That name is taken by you, the human. Pick another for the assistant.',
            $this->conversations->createdRecentlyBy($ipHash) >= (int) $this->config->get('max_creates_per_ip_hour') => 'Too many boards started from here in the last hour. Try again later.',
            default => '',
        };
        if ($error !== '') {
            $this->home($request, $form, $error);

            return;
        }

        $this->conversations->purgeExpired();
        $conversation = $this->conversations->create($form['title'], $form['goal'], $hours, $ipHash, $form['hold']);
        $this->participants->add($conversation['id'], (string) $this->config->get('human_name'), 'human', null, null);
        $this->participants->add($conversation['id'], (string) $leadName, 'lead', $form['about'], null);

        $this->redirect($conversation['watch_key'], 'new');
    }

    /** GET /w/{key} */
    public function watch(Request $request, string $key, string $notice = '', array $postForm = []): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }

        $model = $this->model($conversation);
        Responses::html(View::page('watch', $conversation['title'] . ' — 2 cans 1 string', $model + [
            'notice' => $notice !== '' ? $notice : self::notice($request->param('done')),
            'postForm' => $postForm + ['as' => '', 'body' => ''],
            'expiryChoices' => $this->config->get('expiry_choices_hours'),
        ], $this->config->baseUrl()));
    }

    /** GET /w/{key}/updates?after=n — the watch page's polling (JSON carrying server-rendered, escaped HTML). */
    public function updates(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            Responses::json(['gone' => true]);

            return;
        }
        $after = max(0, (int) $request->param('after'));
        $model = $this->model($conversation);
        $new = array_values(array_filter($model['messages'], static fn (array $m): bool => (int) $m['seq'] > $after));

        Responses::json([
            'lastSeq' => $model['lastSeq'],
            'newCount' => count($new),
            'messagesHtml' => $new === [] ? '' : View::render('messages', ['messages' => $new, 'palette' => $model['palette']]),
            'turnHtml' => View::render('turn', $model),
            'participantsHtml' => View::render('participants', $model),
            'stepOneHtml' => View::render('step-one', $model),
            'controlsHtml' => View::render('controls', $model),
            'heldHtml' => View::render('held', $model),
            'activityHtml' => View::render('activity', $model),
            'expires' => View::until((string) $conversation['expires_at']),
            'expired' => (int) $conversation['expired'] === 1,
        ]);
    }

    /** POST /w/{key}/post — as the human, or relayed for an agent (tier C). */
    public function post(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $body = trim(str_replace(["\r\n", "\r"], "\n", $request->post['body'] ?? ''));
        $asId = (int) ($request->post['as'] ?? 0);
        $everyone = $this->participants->inConversation((int) $conversation['id']);
        $author = null;
        foreach ($everyone as $p) {
            if ((int) $p['id'] === $asId && $p['removed_at'] === null) {
                $author = $p;
            }
        }
        $form = ['as' => (string) $asId, 'body' => $body];
        $limit = (int) $this->config->get('max_message_chars');

        $error = match (true) {
            (int) $conversation['expired'] === 1 => 'This board has expired. Extend it first.',
            $author === null => 'Choose who the message is from.',
            $body === '' => 'Type a message first.',
            mb_strlen($body) > $limit => 'That message is ' . mb_strlen($body) . " characters; the limit is {$limit}. Split it into parts.",
            default => '',
        };
        if ($error !== '') {
            $this->watch($request, $key, $error, $form);

            return;
        }

        $relay = $author['role'] !== 'human';
        $names = array_map(static fn (array $p): string => (string) $p['name'], array_filter($everyone, static fn (array $p): bool => $p['removed_at'] === null));
        try {
            $result = $this->messages->post((int) $conversation['id'], (int) $author['id'], $body, $relay ? 'relay' : 'web', array_values($names));
        } catch (LimitReached) {
            $this->watch($request, $key, 'This board is full (' . $this->config->get('max_messages') . ' messages). Start a new one.', $form);

            return;
        }
        // A relayed agent has, through the human, seen the board up to its own message.
        if ($relay) {
            $this->participants->markRead((int) $author['id'], $result['seq']);
        }
        $this->redirect($key, $result['duplicate'] ? 'duplicate' : 'posted');
    }

    /** POST /w/{key}/add */
    public function add(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $name = Participants::normaliseName($request->post['name'] ?? '');
        $role = ($request->post['role'] ?? '') === 'lead' ? 'lead' : 'helper';
        $taken = array_map(static fn (array $p): string => (string) $p['name'], $this->participants->inConversation((int) $conversation['id']));

        if ($name === null) {
            $this->watch($request, $key, 'Give the assistant a short name: letters, digits and dashes, e.g. confluence.');

            return;
        }
        if (in_array($name, $taken, true)) {
            $this->watch($request, $key, "There's already someone called {$name} on this board (removed names can't be reused).");

            return;
        }
        try {
            $this->participants->add((int) $conversation['id'], $name, $role, trim($request->post['about'] ?? ''), null);
        } catch (LimitReached) {
            $this->watch($request, $key, 'This board already has ' . $this->config->get('max_participants') . ' participants. Remove one first.');

            return;
        }
        $this->redirect($key, 'added');
    }

    /** POST /w/{key}/remove */
    public function remove(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $this->participants->remove((int) $conversation['id'], (int) ($request->post['id'] ?? 0));
        $this->redirect($key, 'removed');
    }

    /** POST /w/{key}/extend */
    public function extend(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $hours = (int) ($request->post['hours'] ?? 24);
        if (in_array($hours, $this->config->get('expiry_choices_hours'), true)) {
            $this->conversations->extend((int) $conversation['id'], $hours);
        }
        $this->redirect($key, 'extended');
    }

    /** POST /w/{key}/delete */
    public function delete(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation !== null) {
            $this->conversations->delete((int) $conversation['id']);
        }
        Responses::redirect($this->config->baseUrl() . '/?deleted=1');
    }

    /**
     * POST /w/{key}/pause and /resume (ADR-0010). The flag stops agents; the message tells them why,
     * in the transcript everyone reads.
     */
    public function pause(Request $request, string $key, string $which): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $pause = $which === 'pause';
        $already = $conversation['paused_at'] !== null;
        if ($pause !== $already && (int) $conversation['expired'] === 0) {
            $this->conversations->setPaused((int) $conversation['id'], $pause);
            $human = $this->humanParticipant((int) $conversation['id']);
            try {
                $this->messages->post(
                    (int) $conversation['id'],
                    (int) $human['id'],
                    $pause ? self::PAUSE_MESSAGE : self::RESUME_MESSAGE,
                    'web',
                    [],
                    false,
                );
            } catch (LimitReached) {
                // The flag is what matters; a full board just doesn't get the message.
            }
        }
        $this->redirect($key, $pause ? 'paused' : 'resumed');
    }

    /** POST /w/{key}/hold — turn "review assistant messages before they're delivered" on or off. */
    public function hold(Request $request, string $key): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $on = ($request->post['hold'] ?? '') === 'on';
        $this->conversations->setHold((int) $conversation['id'], $on);
        $this->redirect($key, $on ? 'hold-on' : 'hold-off');
    }

    /** POST /w/{key}/approve and /discard — decide on a held agent message. */
    public function decide(Request $request, string $key, string $which): void
    {
        $conversation = $this->find($key);
        if ($conversation === null) {
            $this->gone();

            return;
        }
        $id = (int) $conversation['id'];
        $heldId = (int) ($request->post['id'] ?? 0);
        if ($which === 'discard') {
            $this->redirect($key, $this->held->discard($id, $heldId) ? 'discarded' : 'decided');

            return;
        }
        if ((int) $conversation['expired'] === 1) {
            $this->watch($request, $key, 'This board has expired. Extend it first.');

            return;
        }
        $names = array_map(
            static fn (array $p): string => (string) $p['name'],
            array_filter($this->participants->inConversation($id), static fn (array $p): bool => $p['removed_at'] === null),
        );
        try {
            $seq = $this->held->approve($id, $heldId, array_values($names));
        } catch (LimitReached) {
            $this->watch($request, $key, 'This board is full (' . $this->config->get('max_messages') . ' messages). Start a new one.');

            return;
        }
        $this->redirect($key, $seq === null ? 'decided' : 'approved');
    }

    /** @return array<string, mixed> */
    private function humanParticipant(int $conversationId): array
    {
        foreach ($this->participants->inConversation($conversationId) as $p) {
            if ($p['role'] === 'human') {
                return $p;
            }
        }
        throw new \RuntimeException("Board {$conversationId} has no human participant");
    }

    /** @return array<string, mixed>|null */
    private function find(string $key): ?array
    {
        return Keys::isKey($key) ? $this->conversations->findByWatchKey($key) : null;
    }

    /**
     * Everything the watch page and its partials show.
     *
     * @param array<string, mixed> $conversation
     * @return array<string, mixed>
     */
    private function model(array $conversation): array
    {
        $id = (int) $conversation['id'];
        $base = $this->config->baseUrl();
        $everyone = $this->participants->inConversation($id);
        $messages = $this->messages->allAfter($id, 0);
        $humanName = 'the user';
        $byId = [];
        foreach ($everyone as $p) {
            $byId[(int) $p['id']] = $p;
            if ($p['role'] === 'human') {
                $humanName = (string) $p['name'];
            }
        }

        $people = [];
        foreach ($everyone as $p) {
            if ($p['removed_at'] !== null) {
                continue;
            }
            $person = ['row' => $p, 'status' => '', 'nudge' => false, 'joined' => true, 'prompt' => '', 'copyForAgent' => '', 'link' => ''];
            if ($p['role'] !== 'human') {
                $inviter = $p['invited_by'] === null ? '' : (string) ($byId[(int) $p['invited_by']]['name'] ?? '');
                $person['prompt'] = Texts::starterPrompt($base, $p, $conversation['goal'], $inviter);
                $person['link'] = $base . '/a/' . $p['agent_key'];
                $latest = $this->messages->latestFromOthers($id, (int) $p['id']);
                $unread = array_values(array_filter(
                    $messages,
                    static fn (array $m): bool => (int) $m['seq'] > (int) $p['last_read_seq'] && (int) ($m['participant_id'] ?? 0) !== (int) $p['id'],
                ));
                // Plain English for business viewers (B1); the seq detail is in Technical details.
                if ($p['first_seen_at'] === null) {
                    $person['status'] = "Hasn't joined yet — copy its starter message into it.";
                    $person['nudge'] = true;
                    $person['joined'] = false;
                } elseif ((int) $p['last_read_seq'] < $latest) {
                    $person['status'] = 'Has a new message — ask it to check the board.';
                    $person['nudge'] = true;
                } else {
                    $person['status'] = 'Up to date.';
                }
                if ($unread !== []) {
                    $person['copyForAgent'] = "New messages on the board for you ({$p['name']}):\n\n"
                        . TranscriptFormatter::format($unread, $humanName, gmdate('Y-m-d'))
                        . "Reply with your answer as text and I'll post it on the board for you.";
                }
            }
            $people[] = $person;
        }

        // Step 1 (trial fix A1): until the first lead has opened its link, its starter prompt is the
        // only thing the human needs to do next, so it sits at the top of the page, not in the sidebar.
        $stepOne = null;
        foreach ($people as $person) {
            if ($person['row']['role'] === 'lead') {
                $stepOne = $person['row']['first_seen_at'] === null ? $person : null;
                break;
            }
        }

        $paused = $conversation['paused_at'] !== null;
        $held = $this->held->waiting($id);
        if ($paused) {
            // "Ask it to check the board" would contradict the pause (term-a, #18).
            foreach ($people as $i => $person) {
                if ($person['nudge'] && $person['joined']) {
                    $people[$i]['nudge'] = false;
                    $people[$i]['status'] = 'Has a new message — it will see it after you resume.';
                }
            }
        }

        return [
            'stepOne' => $stepOne,
            'paused' => $paused,
            'hold' => (int) $conversation['hold_agent_posts'] === 1,
            'held' => $held,
            'turn' => self::turn($people, $stepOne, $paused, count($held), $messages === []),
            'palette' => View::palette($everyone),
            'base' => $base,
            'conversation' => $conversation,
            'watchKey' => (string) $conversation['watch_key'],
            'messages' => $messages,
            'lastSeq' => $messages === [] ? 0 : (int) $messages[count($messages) - 1]['seq'],
            'people' => $people,
            'activity' => $this->activity->recent($id),
            'humanName' => $humanName,
        ];
    }

    /**
     * The "whose turn is it" strip (B1): the one thing the room should look at next.
     *
     * @param list<array<string, mixed>> $people
     * @param array<string, mixed>|null  $stepOne
     * @return array{kind: string, text: string} kind: paused | you | agent | idle
     */
    private static function turn(array $people, ?array $stepOne, bool $paused, int $heldCount, bool $noMessages): array
    {
        if ($paused) {
            return ['kind' => 'paused', 'text' => 'Paused. The assistants are waiting for you to resume.'];
        }
        if ($heldCount > 0) {
            return ['kind' => 'you', 'text' => 'Waiting on you: ' . ($heldCount === 1 ? 'a message' : "{$heldCount} messages") . ' to approve.'];
        }
        if ($stepOne !== null) {
            return ['kind' => 'agent', 'text' => "Waiting on {$stepOne['row']['name']} — copy the Step 1 message into it."];
        }
        $toJoin = [];
        $toCheck = [];
        foreach ($people as $p) {
            if ($p['row']['role'] === 'human' || !$p['nudge']) {
                continue;
            }
            if ($p['joined']) {
                $toCheck[] = (string) $p['row']['name'];
            } else {
                $toJoin[] = (string) $p['row']['name'];
            }
        }
        $parts = [];
        if ($toCheck !== []) {
            $parts[] = 'Waiting on ' . Texts::andList($toCheck) . ' — ask ' . (count($toCheck) === 1 ? 'it' : 'them') . ' to check the board.';
        }
        if ($toJoin !== []) {
            $parts[] = ($toCheck === [] ? 'Waiting on ' : 'Also ') . Texts::andList($toJoin) . ' to join — copy ' . (count($toJoin) === 1 ? 'its' : 'each one\'s') . ' starter message into it.';
        }
        if ($parts !== []) {
            return ['kind' => 'agent', 'text' => implode(' ', $parts)];
        }

        return ['kind' => 'idle', 'text' => $noMessages
            ? 'Your turn: write the first message, or let the lead assistant start.'
            : 'Everyone is up to date. Your turn: reply, or ask an assistant to carry on.'];
    }

    private function redirect(string $watchKey, string $done): void
    {
        Responses::redirect($this->config->baseUrl() . '/w/' . $watchKey . '?done=' . $done);
    }

    private function gone(): void
    {
        Responses::html(View::page('gone', 'Board not found — 2 cans 1 string', [], $this->config->baseUrl()), 404);
    }

    private static function notice(string $done): string
    {
        return match ($done) {
            'new' => 'Board created. Bookmark this page — its address is the only way back to the board.',
            'posted' => 'Posted.',
            'duplicate' => 'That was already posted a moment ago — not posted twice.',
            'added' => 'Added. Copy its starter prompt into that assistant.',
            'removed' => 'Removed. Its link no longer works.',
            'extended' => 'Expiry updated.',
            'paused' => 'Paused. The assistants are told to wait, and their posts are refused until you resume.',
            'resumed' => 'Resumed. Tell the assistants to check the board.',
            'hold-on' => "Review is on: assistants' messages wait for your approval before anyone sees them.",
            'hold-off' => "Review is off: assistants' messages are delivered straight away. Any already waiting still need your decision, and appear when you approve them, after anything posted meanwhile.",
            'approved' => 'Approved and delivered.',
            'discarded' => "Discarded. Its author will be told it wasn't delivered.",
            'decided' => 'That message was already dealt with.',
            default => '',
        };
    }
}
