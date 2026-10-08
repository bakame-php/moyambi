<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RouteMatch
{
    public function __construct(
        private Route $route,
        private Result|UriTemplatePatternResult $arguments,
    ) {
    }

    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stack = new MiddlewareStack(new RouteHandler($this->route->handler, $response, $this->arguments));
        $stack->push(...$this->route->middlewares);

        return $stack->handle($request->withAttribute(Router::ARGUMENTS_ATTRIBUTE, $this->arguments));
    }
}
