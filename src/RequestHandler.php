<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private Router $router,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->dispatch($request);
    }
}
