<?php

declare(strict_types=1);

use Bakame\Moyambi\Router;
use Bakame\Moyambi\StaticResponseHandler;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Uri\UrlPattern\Result;
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

$router->get('pattern:/hello/:name', function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    Result $routeMatch
) {
    $name = $routeMatch->path->string('name', 'World');
    $response->getBody()->write("Hello, $name");

    return $response;
});

new SapiEmitter()->emit(
    $router->handle(
        new ServerRequestCreator($psrfactory, $psrfactory, $psrfactory, $psrfactory)->fromGlobals()
    )
);