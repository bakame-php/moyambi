<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class StaticResponseHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseInterface $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}
