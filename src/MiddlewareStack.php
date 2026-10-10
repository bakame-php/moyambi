<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_reverse;

final class MiddlewareStack implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares = [];

    public function __construct(private RequestHandlerInterface $handler)
    {
    }

    public function push(MiddlewareInterface ...$middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handler;
        foreach (array_reverse($this->middlewares) as $middleware) {
            $handler = new MiddlewareHandler($middleware, $handler);
        }

        return $handler->handle($request);
    }
}
