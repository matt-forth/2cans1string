<?php

declare(strict_types=1);

// Creates a board from the command line (until the human pages exist) and prints each agent's
// starter prompt. Usage:
//   .\dev\php.ps1 dev\create-board.php "Title" "Goal (optional)" lead-name helper-name [more helpers...]

use Cans\Agent\Texts;
use Cans\Config;
use Cans\Db;
use Cans\Domain\Conversations;
use Cans\Domain\Participants;

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';

$args = array_slice($argv, 1);
if (count($args) < 3) {
    fwrite(STDERR, "Usage: dev\\create-board.php \"Title\" \"Goal\" lead-name [helper-name ...]\n");
    exit(1);
}
[$title, $goal] = $args;
$agentNames = array_slice($args, 2);

$config = Config::load($root);
$db = new Db($config);
$participants = new Participants($db, $config);

$conversation = (new Conversations($db))->create($title, $goal, 24, hash('sha256', 'dev-script'));
$participants->add($conversation['id'], (string) $config->get('human_name'), 'human', null, null);

foreach ($agentNames as $i => $raw) {
    $name = Participants::normaliseName($raw) ?? throw new InvalidArgumentException("Bad name: $raw");
    $agent = $participants->add($conversation['id'], $name, $i === 0 ? 'lead' : 'helper', null, null);
    echo "===== Starter prompt for {$name} ({$agent['role']}) =====\n";
    echo Texts::starterPrompt($config->baseUrl(), $agent, $goal === '' ? null : $goal, '') . "\n\n";
}
echo "Board \"{$title}\" created (expires in 24 h). Watch key: {$conversation['watch_key']} (watch page arrives in slice 2).\n";
