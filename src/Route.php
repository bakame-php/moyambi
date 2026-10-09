<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use BackedEnum;
use Closure;
use League\Uri\UriTemplate;
use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern;
use League\Uri\UrlPattern\Result;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use ValueError;

use function is_string;
use function str_starts_with;
use function strtoupper;
use function substr;
use function trim;

final readonly class Route
{
    /** @var list<non-empty-string> */
    private array $methods;

    /**
     * @param list<non-empty-string> $methods
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function __construct(
        array $methods,
        public UrlPattern|UriTemplate $pattern,
        public Closure $handler,
        public RoutePrecedence $precedence,
        private array $middlewares = [],
    ) {
        foreach ($this->middlewares as $middleware) {
            $middleware instanceof MiddlewareInterface || throw new ValueError('The middleware must implement the MiddlewareInterface');
        }

        $fMethods = [];
        foreach ($methods as $method) {
            (is_string($method) &&  '' !== trim($method)) || throw new ValueError('The method must be a non empty string');
            $fMethods[] = strtoupper($method);
        }
        $this->methods = $fMethods;
    }

    /**
     * @param list<non-empty-string> $methods
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|ExtractionResult): ResponseInterface $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public static function create(
        array $methods,
        UrlPattern|UriTemplate|string $pattern,
        Closure $handler,
        array $middlewares = [],
        RoutePrecedence|int $precedence = 0,
    ): self {

        $pattern = match (true) {
            $pattern instanceof UrlPattern,
            $pattern instanceof UriTemplate => $pattern,
            str_starts_with($pattern, 'pattern:') => UrlPattern::from(substr($pattern, 8)),
            str_starts_with($pattern, 'template:') => new UriTemplate(substr($pattern, 9)),
            default => throw new ValueError('The route string pattern must use the "pattern:" or "template:" prefix.'),
        };

        return new self(
            $methods,
            $pattern,
            $handler,
            $precedence instanceof RoutePrecedence ? $precedence : new RoutePrecedence($precedence),
            $middlewares,
        );
    }

    public function process(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Result|ExtractionResult $routeArgs,
    ): ResponseInterface {
        return new MiddlewareStack(
            handler: new RouteHandler($this->handler, $response, $routeArgs),
            middlewares: $this->middlewares,
        )->handle(
            $request->withAttribute($routeArgs::class, $routeArgs)
        );
    }

    public function acceptsAnyMethod(): bool
    {
        return [] === $this->methods;
    }

    public function supportsMethod(BackedEnum|string $method): bool
    {
        $method = $method instanceof BackedEnum ? strtoupper((string) $method->value) : strtoupper($method);

        return [] === $this->methods
            || in_array(strtoupper($method), $this->methods, true);
    }
}
