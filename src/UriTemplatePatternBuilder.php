<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use BackedEnum;
use League\Uri\UriTemplate;
use League\Uri\UrlPattern\ComponentName;
use Stringable;

final class UriTemplatePatternBuilder
{
    private ?UriTemplate $scheme = null;
    private ?UriTemplate $username = null;
    private ?UriTemplate $password = null;
    private ?UriTemplate $host = null;
    private ?UriTemplate $port = null;
    private ?UriTemplate $path = null;
    private ?UriTemplate $query = null;
    private ?UriTemplate $fragment = null;

    public function __construct(
        UriTemplate|Stringable|BackedEnum|string|null $scheme = null,
        UriTemplate|Stringable|BackedEnum|string|null $username = null,
        UriTemplate|Stringable|BackedEnum|string|null $password = null,
        UriTemplate|Stringable|BackedEnum|string|null $host = null,
        UriTemplate|Stringable|BackedEnum|string|null $port = null,
        UriTemplate|Stringable|BackedEnum|string|null $path = null,
        UriTemplate|Stringable|BackedEnum|string|null $query = null,
        UriTemplate|Stringable|BackedEnum|string|null $fragment = null,
    ) {
        $this->scheme($scheme)
            ->username($username)
            ->password($password)
            ->host($host)
            ->port($port)
            ->path($path)
            ->query($query)
            ->fragment($fragment);
    }

    public function build(): UriTemplatePattern
    {
        return new UriTemplatePattern([
            ComponentName::Scheme->value => $this->scheme,
            ComponentName::Username->value => $this->username,
            ComponentName::Password->value => $this->password,
            ComponentName::Host->value => $this->host,
            ComponentName::Port->value => $this->port,
            ComponentName::Path->value => $this->path,
            ComponentName::Query->value => $this->query,
            ComponentName::Fragment->value => $this->fragment,
        ]);
    }

    public function reset(): self
    {
        $this->scheme = null;
        $this->username = null;
        $this->password = null;
        $this->host = null;
        $this->port = null;
        $this->path = null;
        $this->query = null;
        $this->fragment = null;

        return $this;
    }

    public function scheme(UriTemplate|Stringable|BackedEnum|string|null $scheme): self
    {
        $this->scheme = self::filter($scheme);

        return $this;
    }

    public function username(UriTemplate|Stringable|BackedEnum|string|null $username): self
    {
        $this->username = self::filter($username);

        return $this;
    }

    public function password(UriTemplate|Stringable|BackedEnum|string|null $password): self
    {
        $this->password = self::filter($password);

        return $this;
    }

    public function host(UriTemplate|Stringable|BackedEnum|string|null $host): self
    {
        $this->host = self::filter($host);

        return $this;
    }

    public function port(UriTemplate|Stringable|BackedEnum|string|null $port): self
    {
        $this->port = self::filter($port);

        return $this;
    }

    public function path(UriTemplate|Stringable|BackedEnum|string|null $path): self
    {
        $this->path = self::filter($path);

        return $this;
    }

    public function query(UriTemplate|Stringable|BackedEnum|string|null $query): self
    {
        $this->query = self::filter($query);

        return $this;
    }

    public function fragment(UriTemplate|Stringable|BackedEnum|string|null $fragment): self
    {
        $this->fragment = self::filter($fragment);

        return $this;
    }

    private static function filter(UriTemplate|Stringable|BackedEnum|string|null $component): ?UriTemplate
    {
        return match (true) {
            null === $component => null,
            $component instanceof UriTemplate => $component,
            default => new UriTemplate($component),
        };
    }
}
