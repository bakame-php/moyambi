<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Closure;
use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RouteHandler implements RequestHandlerInterface
{
    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     */
    public function __construct(
        private Closure $handler,
        private ResponseInterface $response,
        private Result|ExtractionResult $arguments,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)($request, $this->response, $this->arguments);
    }
}
