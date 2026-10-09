<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RouteMatch
{
    public function __construct(
        public Route $route,
        public Result|ExtractionResult $arguments,
    ) {
    }

    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->route->process($request, $response, $this->arguments);
    }
}
