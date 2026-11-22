<?php

declare(strict_types=1);

namespace Cans\Http;

/** Ordered routes: first whose method and path regex match wins. Captures are passed to the handler. */
final class Router
{
    /** @var list<array{list<string>, string, callable}> */
    private array $routes = [];

    /** @param list<string> $methods */
    public function add(array $methods, string $pattern, callable $handler): void
    {
        $this->routes[] = [$methods, $pattern, $handler];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as [$methods, $pattern, $handler]) {
            if (preg_match($pattern, $request->path, $captures) !== 1) {
                continue;
            }
            if (!in_array($request->method, $methods, true) && !($request->method === 'HEAD' && in_array('GET', $methods, true))) {
                Responses::text("ERROR: {$request->method} is not supported here.\n", 405);

                return;
            }
            $handler($request, ...array_slice($captures, 1));

            return;
        }

        Responses::text("Not found. This site is 2 cans 1 string — a message board for AI assistants.\n", 404);
    }
}
