<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use Closure;
use League\Uri\UriTemplate;
use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern;
use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

use function strlen;
use function strtoupper;
use function substr;

final class Router implements RequestHandlerInterface
{
    private RouteList $routes;
    /** @var list<MiddlewareInterface> */
    private array $middlewares = [];
    private ?MiddlewareStack $middlewareStack = null;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly RequestHandlerInterface $notFoundHandler,
    ) {
        $this->routes = new RouteList();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->middlewareStack ??= new MiddlewareStack(handler: new RequestHandler($this), middlewares: $this->middlewares);

        return $this->middlewareStack->handle($request);
    }

    public function add(Route $route): self
    {
        $this->routes->add($route);

        return $this;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        $this->middlewareStack = null;

        return $this;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $routeMatch = $this->routes->match($request);

        return null !== $routeMatch
            ? $routeMatch->process($request, $this->responseFactory->createResponse())
            : $this->notFoundHandler->handle($request);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function get(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function post(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function put(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function patch(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function delete(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function query(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function head(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function options(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map(self::httpMethod(__METHOD__), $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function any(UrlPattern|UriTemplate|string $pattern, Closure $handler, array $middlewares = [], RoutePrecedence|int $precedence = 0): self
    {
        return $this->map([], $pattern, $handler, $middlewares, $precedence);
    }

    /**
     * @param list<non-empty-string> $methods
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function map(
        array $methods,
        UrlPattern|UriTemplate|string $pattern,
        Closure $handler,
        array $middlewares = [],
        RoutePrecedence|int $precedence = 0
    ): self {
        return $this->add(Route::create($methods, $pattern, $handler, $middlewares, $precedence));
    }

    /**
     * @param non-empty-string $method
     *
     * @return list<non-empty-string>
     */
    private static function httpMethod(string $method): array
    {
        $res = strtoupper(substr($method, strlen(self::class) + 2));

        return '' !== $res ? [$res] : throw new ValueError('The HTTP method "'.$method.'" is invalid.');
    }
}
