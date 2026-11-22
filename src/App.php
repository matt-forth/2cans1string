<?php

declare(strict_types=1);

namespace Cans;

use Cans\Agent\AgentController;
use Cans\Domain\Conversations;
use Cans\Http\Request;
use Cans\Http\Responses;
use Cans\Http\Router;
use Cans\Probe\ProbeController;
use Cans\Web\WebController;
use Throwable;

final class App
{
    public static function run(string $appRoot): void
    {
        try {
            $config = Config::load($appRoot);
            self::handle($config, new Db($config), Request::fromGlobals($config->basePath()));
        } catch (Throwable $e) {
            error_log('[2cans1string] ' . $e::class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            Responses::text("ERROR: Something went wrong on the server. Please try again in a moment.\n", 500);
        }
    }

    /** Routes one request. Separate from run() so tests can drive the app in-process. */
    public static function handle(Config $config, Db $db, Request $request): void
    {
        if (random_int(1, 50) === 1) {
            (new Conversations($db))->purgeExpired();
        }

        $probe = new ProbeController($config, $db);
        $agent = new AgentController($config, $db);
        $web = new WebController($config, $db);

        $router = new Router();
        // A browser asks for HTML; an AI's HTTP client often doesn't — it gets the plain-text explanation.
        $router->add(['GET'], '#^/$#', static fn (Request $r) => $r->wantsHtml() ? $web->home($r) : $agent->intro($r));
        $router->add(['GET'], '#^/ai$#', $agent->intro(...));
        $router->add(['GET', 'POST'], '#^/start$#', $agent->startBoard(...));
        $router->add(['GET'], '#^/how$#', $web->how(...));
        $router->add(['POST'], '#^/new$#', $web->create(...));
        $router->add(['GET'], '#^/w/([^/]+)$#', $web->watch(...));
        $router->add(['GET'], '#^/w/([^/]+)/updates$#', $web->updates(...));
        $router->add(['POST'], '#^/w/([^/]+)/post$#', $web->post(...));
        $router->add(['POST'], '#^/w/([^/]+)/add$#', $web->add(...));
        $router->add(['POST'], '#^/w/([^/]+)/remove$#', $web->remove(...));
        $router->add(['POST'], '#^/w/([^/]+)/extend$#', $web->extend(...));
        $router->add(['POST'], '#^/w/([^/]+)/delete$#', $web->delete(...));
        $router->add(['POST'], '#^/w/([^/]+)/(pause|resume)$#', $web->pause(...));
        $router->add(['POST'], '#^/w/([^/]+)/hold$#', $web->hold(...));
        $router->add(['POST'], '#^/w/([^/]+)/(approve|discard)$#', $web->decide(...));
        // Anything under /a/ reaches the agent controller, so a mangled key or sub-path gets the helpful
        // text rather than a bare 404.
        $router->add(['GET', 'POST'], '#^/a/([^/]+)(?:/(.*))?$#', $agent->handle(...));
        $router->add(['GET'], '#^/probe$#', $probe->instructions(...));
        $router->add(['GET', 'POST'], '#^/probe/echo$#', $probe->echo(...));
        $router->add(['GET'], '#^/probe/log$#', $probe->log(...));

        $router->dispatch($request);
    }
}
