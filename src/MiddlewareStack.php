<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

use function array_reverse;

final readonly class MiddlewareStack implements RequestHandlerInterface
{
    /**
     * @param list<MiddlewareInterface> $middlewares
     */
    public function __construct(
        private RequestHandlerInterface $handler,
        private array $middlewares
    ) {
        foreach ($this->middlewares as $middleware) {
            $middleware instanceof MiddlewareInterface || throw new ValueError('The middleware must implement the MiddlewareInterface');
        }
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
