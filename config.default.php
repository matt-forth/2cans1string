<?php

declare(strict_types=1);

// Committed defaults. Override any of these in config.local.php (git-ignored).
// Meanings: docs/architecture/implementation.md, "Configuration".

return [
    'base_url' => '',          // required in config.local.php, e.g. https://cans.example.com (no trailing slash)
    'secret' => '',            // required: long random string; salts IP hashes
    'probe_key' => '',         // required: guards /probe/log?k=...
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=cans;charset=utf8mb4',
        'user' => 'cans',
        'password' => '',
    ],
    'human_name' => 'human',
    'max_message_chars' => 8000,
    'max_participants' => 12,
    'max_messages' => 500,
    'max_creates_per_ip_hour' => 30,
    'dedupe_minutes' => 10,
    'page_messages' => 20,
    'page_chars' => 12000,
    'expiry_choices_hours' => [1, 24, 168],
];
