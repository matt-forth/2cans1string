<?php

declare(strict_types=1);

// Optional cron job (docs/deploy.md): deletes expired boards and old probe hits.
// Not needed: the site already does this on its own as requests arrive (ADR-0008). Use it if a
// board must disappear promptly even when nobody visits the site.
//   crontab:  17 * * * *  php /path/to/app/bin/purge.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$appRoot = dirname(__DIR__);
require $appRoot . '/src/bootstrap.php';

$db = new Cans\Db(Cans\Config::load($appRoot));
$removed = (new Cans\Domain\Conversations($db))->purgeExpired();
$probes = $db->run('DELETE FROM probe_hits WHERE at < UTC_TIMESTAMP() - INTERVAL 7 DAY')->rowCount();
echo gmdate('Y-m-d H:i:s') . " UTC: deleted {$removed} expired board(s) and {$probes} old probe hit(s).\n";
