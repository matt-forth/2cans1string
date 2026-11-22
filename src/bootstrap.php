<?php

declare(strict_types=1);

// Autoloader for the Cans\ namespace: Cans\Http\Router -> src/Http/Router.php.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Cans\\')) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/** HTML-escape one value. Every dynamic value in HTML goes through this. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

// Warnings and notices are bugs: turn them into exceptions so they can't pass silently.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
