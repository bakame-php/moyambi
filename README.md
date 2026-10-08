Moyambi
======

**Moyambi** is a simple lightweight PSR-15 router using:

- [UrlPattern matching](https://urlpattern.spec.whatwg.org/).
- [UriTemplate extraction](https://uri.thephpleague.com/uri/7.0/uri-template/#variable-extraction)

You can use:
- `League\Uri\UrlPattern` instances
- `League\Uri\UriTemplate` instances
- or string starting with `pattern:` or `template:` to specify the route to select.

When using `UriTemplate` only the URI path is considered for routing. When using `URLPattern`
the full URI object **CAN** be used depending on the `URLPattern` instance used.

```php
<?php

use Bakame\Moyambi\Router;
use Bakame\Moyambi\StaticResponseHandler;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Uri\UrlPattern\Result;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface as Request;
use Psr\Http\Message\ServerRequestInterface as Response;

require __DIR__ . '/vendor/autoload.php';

$factory = new Psr17Factory();
$router = new Router(
    responseFactory: $factory,
    notFoundHandler: new StaticResponseHandler(
        $factory
            ->createResponse(404)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($factory->createStream('<h1>Not Found</h1>'))
    ),
);

$router->get('pattern:/hello/:name', function (Request $request, Response $response, Result $routeMatch) {
    $body = $response->getBody();
    $body->write('Hello '.$routeMatch->path->string('name', 'World').'!');
    
    return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/html')
            ->withBody($body);
});

$request =  new ServerRequestCreator(
    serverRequestFactory: $factory,
    uriFactory: $factory,
    uploadedFileFactory: $factory,
    streamFactory: $factory,
)->fromGlobals()

new SapiEmitter()->emit($router->handle($request));
```

To work as expected **Bakame\Moyambi** requires:

- PHP8.4+
- `league\uri` v7.9+
- A `PSR-7`, `PRS-15`, `PSR-17` implementing libraries
- an HTTP handler runner to convert and emit the response.

Happy coding!