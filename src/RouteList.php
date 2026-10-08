<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

final class RouteList
{
    /** @var array<non-empty-string, list<Route>> */
    private array $routes = [];

    /** @var list<Route> */
    private array $anyRoutes = [];

    public function add(Route ...$routes): self
    {
        foreach ($routes as $route) {
            if ($route->acceptsAnyMethod()) {
                $this->anyRoutes[] = $route;
                continue;
            }

            foreach ($route->methods as $method) {
                $this->routes[$method][] = $route;
            }
        }

        return $this;
    }

    public function match(ServerRequestInterface $request): ?RouteMatch
    {
        $uri = $request->getUri();
        $routes = $this->routes[$request->getMethod()] ?? [];
        $matched = $this->matchRoute($routes, $uri);

        return null !== $matched ? $matched : $this->matchRoute($this->anyRoutes, $uri);
    }

    /**
     * @param list<Route> $routes
     */
    private function matchRoute(array $routes, UriInterface $uri): ?RouteMatch
    {
        $matched = null;
        foreach ($routes as $route) {
            $arguments = $route->pattern->extract($uri);
            if (null === $arguments) {
                continue;
            }

            $matchedRoute = new RouteMatch($route, $arguments);
            if (RoutePrecedence::Regular === $route->precedence) {
                return $matchedRoute;
            }

            $matched = $matchedRoute;
        }

        return $matched;
    }
}
