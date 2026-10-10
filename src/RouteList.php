<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use IteratorAggregate;
use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern;
use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Traversable;

/**
 * @implements IteratorAggregate<Route>
 */
final class RouteList implements IteratorAggregate
{
    /** @var list<Route> */
    private array $routes = [];

    public function add(Route ...$routes): self
    {
        foreach ($routes as $route) {
            $this->routes[] = $route;
        }

        return $this;
    }

    /**
     * @return Traversable<Route>
     */
    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }

    public function match(ServerRequestInterface $request): ?RouteMatch
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ('/' !== $path && str_ends_with($path, '/')) {
            $path = substr($path, 0, -1);
        }

        $method = $request->getMethod();
        $matched = null;
        foreach ($this as $route) {
            $routeArguments = $this->routeMatch($route, $method, $uri, $path);
            if (null === $routeArguments) {
                continue;
            }

            $routeMatch = new RouteMatch($route, $routeArguments);
            if (RoutePrecedence::Regular === $route->precedence && !$route->acceptsAnyMethod()) {
                return $routeMatch;
            }

            $matched = $routeMatch;
        }

        return $matched;
    }

    private function routeMatch(Route $route, string $method, UriInterface $uri, string $path): Result|ExtractionResult|null
    {
        if (!$route->supportsMethod($method)) {
            return null;
        }

        if ($route->pattern instanceof UrlPattern) {
            return $route->pattern->extract($uri);
        }

        $result = $route->pattern->extract($path);

        return $result->isSuccessful() ? $result : null;
    }
}
