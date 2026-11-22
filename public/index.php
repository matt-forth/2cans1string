<?php

declare(strict_types=1);

// Front controller. Everything that isn't a real file under public/ arrives here.

// Built-in dev server: let it serve real static files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

// The application lives next to public/ (default), or in public/app/ on hosts that only allow one
// web-root folder (that folder is denied to the web by its own .htaccess).
$appRoot = is_file(dirname(__DIR__) . '/src/bootstrap.php') ? dirname(__DIR__) : __DIR__ . '/app';

require $appRoot . '/src/bootstrap.php';

Cans\App::run($appRoot);
