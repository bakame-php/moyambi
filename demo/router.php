<?php

declare(strict_types=1);

use Bakame\Moyambi\Router;
use Bakame\Moyambi\StaticResponseHandler;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Uri\UrlPattern\Result;
use League\Uri\UrlPatternBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

$psrfactory = new Psr17Factory();
$router = new Router(
    responseFactory: $psrfactory,
    notFoundHandler: new StaticResponseHandler(
        $psrfactory
            ->createResponse(404)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($psrfactory->createStream('<h1>Not Found</h1>'))
    ),
);

$pattern = UrlPatternBuilder::from('/hello/{:name}')
    ->host('{:subdomain.}?localhost')
    ->build();

$router->get($pattern, function (ServerRequestInterface $request, ResponseInterface $response) {
    /** @var Result $routeArgs */
    $routeArgs = $request->getAttribute(Result::class);
    $name = $routeArgs->path->string('name', 'World');
    $subdomain = $routeArgs->host->string('subdomain', 'www');
    $port = $routeArgs->port->implicit();

    $response->getBody()->write("Hello, $name; Welcome to $subdomain; via port $port");

    return $response;
});

new SapiEmitter()->emit(
    $router->handle(
        new ServerRequestCreator($psrfactory, $psrfactory, $psrfactory, $psrfactory)->fromGlobals()
    )
);