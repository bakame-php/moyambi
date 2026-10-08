<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use BackedEnum;
use Closure;
use League\Uri\UrlPattern;
use League\Uri\UrlPattern\Result;
use League\Uri\UrlPatternBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use ValueError;

use function is_string;
use function strtoupper;
use function substr;
use function trim;

final readonly class Route
{
    /** @var list<non-empty-string> */
    public array $methods;
    /** @var list<MiddlewareInterface> */
    public array $middlewares;

    /**
     * @param list<mixed> $methods
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|UriTemplatePatternResult): ResponseInterface $handler
     * @param list<mixed> $middlewares
     */
    public function __construct(
        array $methods,
        public UrlPattern|UriTemplatePattern $pattern,
        public Closure $handler,
        public RoutePrecedence $precedence,
        array $middlewares = [],
    ) {
        $fMiddlewares = [];
        foreach ($middlewares as $middleware) {
            $middleware instanceof MiddlewareInterface || throw new ValueError('The middleware must implement the MiddlewareInterface');
            $fMiddlewares[] = $middleware;
        }

        $fMethods = [];
        foreach ($methods as $method) {
            (is_string($method) &&  '' !== trim($method)) || throw new ValueError('The method must be a non empty string');
            $fMethods[] = strtoupper($method);
        }

        $this->methods = $fMethods;
        $this->middlewares = $fMiddlewares;
    }

    /**
     * @param list<mixed> $methods
     * @param Closure(ServerRequestInterface, ResponseInterface, Result|UriTemplatePatternResult): ResponseInterface $handler
     * @param list<mixed> $middlewares
     */
    public static function create(
        array $methods,
        UrlPattern|UriTemplatePattern|string $pattern,
        Closure $handler,
        array $middlewares = [],
        RoutePrecedence $precedence = RoutePrecedence::Regular,
    ): self {
        return new self(
            $methods,
            match (true) {
                $pattern instanceof UrlPattern,
                $pattern instanceof UriTemplatePattern => $pattern,
                str_starts_with($pattern, 'pattern:') => new UrlPatternBuilder(path: substr($pattern, 8))->build(),
                str_starts_with($pattern, 'template:') => new UriTemplatePatternBuilder(path: substr($pattern, 9))->build(),
                default => throw new ValueError('The pattern must be a non empty string starting with "pattern:" or "template:"'),
            },
            $handler,
            $precedence,
            $middlewares,
        );
    }

    public function acceptsAnyMethod(): bool
    {
        return [] === $this->methods;
    }

    public function supportsMethod(BackedEnum|string $method): bool
    {
        return $this->acceptsAnyMethod() || in_array(
            strtoupper($method instanceof BackedEnum ? (string) $method->value : $method),
            $this->methods,
            true
        );
    }
}
