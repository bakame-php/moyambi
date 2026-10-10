<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern\Result;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

use function assert;

final class RouterTest extends TestCase
{
    private Psr17Factory $factory;
    private Router $router;
    private StaticResponseHandler $notFoundHandler;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->notFoundHandler = new StaticResponseHandler(
            $this->factory
                ->createResponse(404)
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->factory->createStream('<h1>Not Found</h1>'))
        );
        $this->router = new Router($this->factory, $this->notFoundHandler);
    }

    /**
     * @param list<string> $events
     *
     */
    private function middleware(array &$events, string $before, string $after): MiddlewareInterface
    {
        return new class ($events, $before, $after) implements MiddlewareInterface {
            /**
             * @param list<string> $events
             */
            public function __construct(
                private array &$events, /* @phpstan-ignore-line */
                private string $start,
                private string $end,
            ) {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->events[] = $this->start;

                $response = $handler->handle($request);

                $this->events[] = $this->end;

                return $response;
            }
        };
    }

    #[Test]
    public function it_dispatches_a_request_to_a_matching_route(): void
    {
        $this->router->get('pattern:/users/:id', static function (ServerRequestInterface $request, ResponseInterface $response, Result|ExtractionResult $routeArgs): ResponseInterface {

            assert($routeArgs instanceof Result);
            $body = $response->getBody();
            $body->write((string) $routeArgs->path->string('id'));

            return $response->withBody($body);
        });

        $response = $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/users/42'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('42', (string) $response->getBody());
    }

    #[Test]
    public function it_dispatches_a_request_to_the_not_foud_handler(): void
    {
        $this->router->get('pattern:/users/:id', static function (ServerRequestInterface $request, ResponseInterface $response, Result|ExtractionResult $routeArgs): ResponseInterface {
            assert($routeArgs instanceof Result);
            $body = $response->getBody();
            $body->write((string) $routeArgs->path->string('id'));

            return $response->withBody($body);
        });

        $response = $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/not/found'));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function it_tests_the_highest_precedence_matching_route_wins(): void
    {
        $this->router
            ->get(
                'pattern:/users/:id',
                static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('generic');

                    return $response->withBody($body);
                },
                precedence: RoutePrecedence::Lower,
            )
            ->get(
                'pattern:/users/me',
                static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('specific');

                    return $response->withBody($body);
                },
            );

        $response = $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/users/me'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('specific', (string) $response->getBody());
    }

    #[Test]
    public function it_tests_first_matching_route_wins_when_precedence_is_equal(): void
    {
        $this->router
            ->get(
                'pattern:/users/:id',
                static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('first');

                    return $response->withBody($body);
                },
            )
            ->get(
                'pattern:/users/me',
                static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('last');

                    return $response->withBody($body);
                },
            );

        $response = $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/users/me'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('first', (string) $response->getBody());
    }

    #[Test]
    #[DataProvider('providesHttpMethods')]
    public function it_respects_http_methods(string $method): void
    {
        $handler = static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            $body = $response->getBody();
            $body->write($request->getMethod());

            return $response->withBody($body);
        };

        $this->router
            ->get('pattern:/users', $handler)
            ->post('pattern:/users', $handler)
            ->patch('pattern:/users', $handler)
            ->delete('pattern:/users', $handler)
            ->options('pattern:/users', $handler)
            ->head('pattern:/users', $handler)
            ->query('pattern:/users', $handler)
            ->put('pattern:/users', $handler);

        $response = $this->router->handle($this->factory->createServerRequest($method, 'https://example.com/users'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($method, (string) $response->getBody());
    }

    /**
     * @return array<non-empty-string, list<non-empty-string>>
     */
    public static function providesHttpMethods(): array
    {
        return [
            'get' => ['GET'],
            'post' => ['POST'],
            'put' => ['PUT'],
            'delete' => ['DELETE'],
            'patch' => ['PATCH'],
            'head' => ['HEAD'],
            'options' => ['OPTIONS'],
            'query' => ['QUERY'],
        ];
    }

    #[Test]
    public function it_prefers_a_specific_http_method_over_any(): void
    {
        $this->router
            ->any(
                'pattern:/users',
                static function (
                    ServerRequestInterface $request,
                    ResponseInterface $response,
                ): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('any');

                    return $response->withBody($body);
                },
            )
            ->get(
                'pattern:/users',
                static function (
                    ServerRequestInterface $request,
                    ResponseInterface $response,
                ): ResponseInterface {
                    $body = $response->getBody();
                    $body->write('get');

                    return $response->withBody($body);
                },
            );

        $response = $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/users'));

        self::assertSame('get', (string) $response->getBody());
    }

    #[Test]
    public function it_processes_route_middleware(): void
    {
        $events = [];
        $middleware = $this->middleware($events, 'before-route-middleware', 'after-route-middleware');

        $this->router->get(
            'pattern:/users',
            static function (ServerRequestInterface $request, ResponseInterface $response) use (&$events): ResponseInterface {
                $events[] = 'inside-route-handler';

                return $response;
            },
            [$middleware],
        );

        $this->router->handle($this->factory->createServerRequest('GET', 'https://example.com/users'));

        self::assertSame([
            'before-route-middleware',
            'inside-route-handler',
            'after-route-middleware',
        ], $events);
    }

    #[Test]
    public function it_processes_global_middleware(): void
    {
        $events = [];

        $middleware = $this->middleware($events, 'before-global-middleware', 'after-global-middleware');

        $this->router
            ->addMiddleware($middleware)
            ->get(
                'pattern:/users',
                static function (ServerRequestInterface $request, ResponseInterface $response) use (&$events): ResponseInterface {
                    $events[] = 'inside-route-handler';

                    return $response;
                },
            );

        $request = $this->factory->createServerRequest('GET', 'https://example.com/users');
        $this->router->handle($request);

        self::assertSame([
            'before-global-middleware',
            'inside-route-handler',
            'after-global-middleware',
        ], $events);
    }

    #[Test]
    public function it_processes_global_and_route_middleware(): void
    {
        $events = [];
        $globalMiddleware = $this->middleware($events, 'before-global-middleware', 'after-global-middleware');
        $routeMiddleware = $this->middleware($events, 'before-route-middleware', 'after-route-middleware');

        $this
            ->router
            ->get(
                'template:/users',
                static function (ServerRequestInterface $request, ResponseInterface $response) use (&$events): ResponseInterface {
                    $events[] = 'inside-route-handler';

                    return $response;
                },
                [$routeMiddleware],
            )
            ->addMiddleware($globalMiddleware);

        $request = $this->factory->createServerRequest('GET', 'https://example.com/users');
        $this->router->handle($request);

        self::assertSame([
            'before-global-middleware',
            'before-route-middleware',
            'inside-route-handler',
            'after-route-middleware',
            'after-global-middleware',
        ], $events);
    }

    #[Test]
    public function it_fails_to_add_a_route_with_an_invalid_prefix(): void
    {
        $this->expectException(ValueError::class);

        $this->router->get('/users', static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $response);
    }

    #[Test]
    public function it_ignores_a_single_trailing_slash(): void
    {
        $this
            ->router
            ->get(
                'template:/users/{id}/found',
                static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                    $routeArgs = $request->getAttribute(ExtractionResult::class);
                    self::assertInstanceOf(ExtractionResult::class, $routeArgs);
                    $body = $response->getBody();
                    $body->write((string) $routeArgs->string('id', '24'));

                    return $response->withBody($body);
                },
            );

        $request = $this->factory->createServerRequest('GET', 'https://example.com/users/42/found/');
        $response = $this->router->handle($request);

        self::assertSame('42', (string) $response->getBody());
    }

    #[Test]
    public function it_does_not_ignore_multiple_trailing_slashes(): void
    {
        $this->router->get('template:/users/{id}/found', static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $response);

        $request = $this->factory->createServerRequest('GET', 'https://example.com/users/42/found//');
        $response = $this->router->handle($request);

        self::assertSame(404, $response->getStatusCode());
    }
}
