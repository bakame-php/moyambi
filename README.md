Moyambi
======

**Moyambi** is a lightweight PSR-15 router built as a proof of concept to explore the integration of two URI-matching strategies within a routing system:

- [URL Pattern](https://urlpattern.spec.whatwg.org/), implemented by `League\Uri\UrlPattern`, for matching URL components.
- [URI Template](https://uri.thephpleague.com/uri/7.0/uri-template/#variable-extraction), implemented by `League\Uri\UriTemplate`, for template-based variable extraction.

## Purpose

The purpose of this proof of concept is to evaluate whether URL Pattern matching and URI Template extraction can be used as route-matching mechanisms within an HTTP router.

**Moyambi** demonstrates how each mechanism can participate in route selection while preserving its respective matching semantics and extraction results.

An additional benefit is that both mechanisms can coexist within the same router, allowing each route to use the strategy best suited to its requirements.

## Routing beyond the path

Unlike conventional routers that primarily match routes against the request path, **Moyambi** allows routes to be matched against URI components, including the scheme, hostname, port, path, query, and fragment.

This is made possible by integrating `League\Uri\UrlPattern` and URI Template extraction through Moyambi's `UriTemplatePattern`. While URL Pattern natively supports matching individual URL components, `UriTemplatePattern` integrates URI Template extraction into Moyambi's component-aware routing model.

This allows both strategies to participate in route selection beyond path-only matching, while preserving their respective syntax and extraction semantics.

### Defining routes

**Moyambi** supports two ways to define a route:

- **Prefixed strings:** `pattern:` selects URL Pattern matching, while `template:` selects URI Template matching. The string following the prefix is interpreted as a path pattern, keeping route definitions aligned with the traditional path-based routing model.
- **Pre-built pattern objects:** Passing a `UrlPattern` or `UriTemplatePattern` instance allows you to configure the matching pattern explicitly. This enables routing based on URI components beyond the path, according to the capabilities of the selected pattern.

The prefixes make the matching strategy explicit and avoid ambiguity between the two syntaxes. Strings without either prefix are rejected with a `ValueError`.

For example:

```php
$router
    ->get('pattern:/hello/:name', $handler)
    ->get('template:/bye/{name}', $handler);
```

For component-aware routing, you can instead build a pattern explicitly and pass the resulting object to the router.

## Route arguments

When a route matches, **Moyambi** makes the result of the selected matching strategy available to the handler in two ways:

- **Handler argument injection:** the matching result is passed directly as an argument to the handler.
- **Request attribute:** the matching result can be retrieved from the request using `Router::ARGUMENTS_ATTRIBUTE`.

The result type depends on the selected matching strategy:

- **URI Template:** `Bakame\Moyambi\UriTemplatePatternResult`
- **URL Pattern:** `League\Uri\UrlPattern\Result`

Both result types offer a similar overall API for accessing extracted values, but retain the data structures and semantics of their respective specifications.
This allows handlers to work with the result of each strategy without requiring the router to normalize both into a single representation.

### Accessing URI component results

Both `UriTemplatePatternResult` and `League\Uri\UrlPattern\Result` expose the matching results through properties named after the corresponding URI components:

- `scheme`
- `username`
- `password`
- `host`
- `port`
- `path`
- `query`
- `fragment`

Each property provides access to the result associated with that component. The result type depends on the matching strategy used: `UriTemplatePatternResult` exposes `ExtractionResult` instances, while URL Pattern exposes its corresponding component results.

Values can be accessed and converted using the result object's API. For example:

```php
$name = $arguments->path->string('name');
```

This retrieves the extracted `name` variable from the path component as a string.

The same access pattern applies to both matching strategies, while the available data and extraction semantics remain specific to each result type.


## Examples

```php
<?php

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

// This route matches multiple components of the request URI.
// The host, port, and path must all match for the route to be selected.
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
```

### 1. URI Template route

**Request**

`GET http://api.localhost:4000/search/john/doe/file.jpg`

**Response body**

```html
<p>You are at: <strong>file</strong> and went through <strong>john ; doe</strong> to reach it!</p>
```

### 2. URI Template route — same path, different host

**Request**

`GET http://shop.localhost:4000/search/john/doe/file.jpg`

**Response body**

```html
<p>You are at: <strong>file</strong> and went through <strong>john ; doe</strong> to reach it!</p>
```

Both requests produce the same response because the route is defined using the template: string prefix.
The router matches the request path without using the hostname to select the route.

### 3. URL Pattern route — `api.localhost`

**Request**

`GET http://api.localhost:4000/hello/john`

**Response body**

```html
<p>Hello, <strong>john</strong> Welcome to <strong>api</strong>; via port <strong>4000</strong></p>
```

### 4. URL Pattern route — `shop.localhost`

**Request**

`GET http://shop.localhost:4000/hello/john`

**Response body**

```html
<p>Hello, <strong>john</strong> Welcome to <strong>shop</strong>; via port <strong>4000</strong></p>
```

These requests demonstrate that the same URL Pattern route can extract different subdomains
while matching the same path structure.

### 4. Unmatched route

**Request**

`GET http://api.localhost:4000/unknown/route`

**Response body**

```html
<h1>Not Found</h1>
```

## Requirements

- PHP 8.4 or later.
- `league/uri` 7.9 or later.
- `PSR-7`, `PSR-15`, and `PSR-17` implementations.

The standalone example additionally uses an HTTP handler runner to emit the generated response.