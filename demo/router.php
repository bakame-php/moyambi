<?php

declare(strict_types=1);

use Bakame\Moyambi\Router;
use Bakame\Moyambi\StaticResponseHandler;
use Bakame\Moyambi\UriTemplatePatternResult;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Uri\UrlPattern\Result;
use League\Uri\UrlPatternBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

function esc(array|Stringable|string|float|int|null $value): array|string
{
    if (!is_array($value)) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    foreach ($value as $key => $item) {
        $value[$key] = esc($item);
    }

    return $value;
}

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

$router
    ->get(
        'template:/search{/segments*}/{file}{.extensions*}', // URI Template expression matched against the URI path.
        function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            /** @var UriTemplatePatternResult $arguments */
            $arguments = $request->getAttribute(Router::ARGUMENTS_ATTRIBUTE);

            $body = $response->getBody();
            $body->write(
                '<p>You are at: <strong>'
                .esc($arguments->path->string('file'))
                .'</strong> and went through <strong>'
                .implode(' ; ', esc($arguments->path->strings('segments')))
                .'</strong> to reach it!</p>'
            );

            return $response->withBody($body);
        });

$pattern = new UrlPatternBuilder(
    host: '{:subdomain.}?localhost',
    port: '*',
    path: '/hello/{:name}'
)->build();

$router->get($pattern, function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    UriTemplatePatternResult|Result $arguments,
) {
    $body = $response->getBody();
    $body->write(
        "<p>Hello, <strong>"
        .esc($arguments->path->string('name', 'World'))
        ."</strong> Welcome to <strong>"
        .esc($arguments->host->string('subdomain', 'www'))
        ."</strong>; via port <strong>"
        .esc($arguments->port[0])
        .'</strong></p>'
    );

    return $response->withBody($body);
});

$request = new ServerRequestCreator($psrfactory, $psrfactory, $psrfactory, $psrfactory)->fromGlobals();
new SapiEmitter()->emit($router->handle($request));