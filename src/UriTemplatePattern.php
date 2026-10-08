<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use BackedEnum;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;
use League\Uri\UriTemplate;
use League\Uri\UriTemplate\ExtractedValue;
use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern\ComponentName;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;
use ValueError;

use function array_key_exists;
use function array_map;
use function explode;

final class UriTemplatePattern
{
    /** @var array<value-of<ComponentName>, ?UriTemplate> $components */
    private readonly array $components;
    public string $scheme { get => (string) $this->components[ComponentName::Scheme->value]; }
    public string $username { get => (string) $this->components[ComponentName::Username->value] ; }
    public string $password { get => (string) $this->components[ComponentName::Password->value] ; }
    public string $host { get => (string) $this->components[ComponentName::Host->value]; }
    public string $port { get => (string) $this->components[ComponentName::Port->value]; }
    public string $path { get => (string) $this->components[ComponentName::Path->value]; }
    public string $query { get => (string) $this->components[ComponentName::Query->value]; }
    public string $fragment { get => (string) $this->components[ComponentName::Fragment->value]; }

    /**
     * @param array<non-empty-string, mixed> $patternComponents
     */
    public function __construct(array $patternComponents)
    {
        $components = [];
        foreach (ComponentName::cases() as $name) {
            if (! array_key_exists($name->value, $patternComponents)) {
                if (ComponentName::Port !== $name) {
                    $components[$name->value] = null;
                    continue;
                }

                $components[ComponentName::Port->value] = isset($patternComponents[ComponentName::Host->value]) ? new UriTemplate('') : null;
                continue;
            }

            $component = $patternComponents[$name->value];
            null === $component || $component instanceof UriTemplate || throw new ValueError('the component must be a "'.UriTemplate::class.'" or null; '.get_debug_type($component).' given.');
            $components[$name->value] = $component;
        }

        $this->components = $components;
    }

    /**
     * @throws SyntaxError
     */
    public function extract(Rfc3986Uri|WhatWgUrl|BackedEnum|Stringable|string $input): ?UriTemplatePatternResult
    {
        $components = self::components($input);
        $result = [];
        $empty = ExtractionResult::empty();
        foreach ($this->components as $name => $component) {
            if (null === $component) {
                $value = $components[$name];
                $result[$name] = '' !== $value ? ExtractionResult::success(['0' => ExtractedValue::fromString($value)]) : $empty;
                continue;
            }

            $found = $component->extract($components[$name]);
            if (!$found->isSuccessful()) {
                return null;
            }

            $result[$name] = $found;
        }

        return UriTemplatePatternResult::tryFrom($result);
    }

    /**
     * @throws SyntaxError
     *
     * @return array{
     *     scheme: string,
     *     username: string,
     *     password: string,
     *     host: string,
     *     port: string,
     *     path: string,
     *     query: string,
     *     fragment: string,
     * }
     */
    private static function components(WhatWgUrl|Rfc3986Uri|Stringable|BackedEnum|string $input): array
    {
        if ($input instanceof WhatWgUrl || $input instanceof UriInterface || $input instanceof Rfc3986Uri) {
            return [
                ComponentName::Scheme->value => (string) $input->getScheme(),
                ComponentName::Username->value => (string) $input->getUsername(),
                ComponentName::Password->value => (string) $input->getPassword(),
                ComponentName::Host->value => (string) ($input instanceof WhatWgUrl ? $input->getAsciiHost() : $input->getHost()),
                ComponentName::Port->value => (string) $input->getPort(),
                ComponentName::Path->value => $input->getPath(),
                ComponentName::Query->value => (string) $input->getQuery(),
                ComponentName::Fragment->value => (string) $input->getFragment(),
            ];
        }

        if ($input instanceof Psr7UriInterface) {
            $username = '';
            $password = '';
            $userInfo = $input->getUserInfo();
            if ('' !== $userInfo) {
                [$username, $password] = explode(':', $userInfo, 2) + [1 => ''];
            }

            return [
                ComponentName::Scheme->value => $input->getScheme(),
                ComponentName::Username->value => $username,
                ComponentName::Password->value => $password,
                ComponentName::Host->value => $input->getHost(),
                ComponentName::Port->value => (string) $input->getPort(),
                ComponentName::Path->value => $input->getPath(),
                ComponentName::Query->value => $input->getQuery(),
                ComponentName::Fragment->value => $input->getFragment(),
            ];
        }

        $components = array_map(static fn (string|int|null $value): string => (string)$value, UriString::parse($input));
        $components[ComponentName::Username->value] = $components['user'];
        $components[ComponentName::Password->value] = $components['pass'];

        return $components;
    }
}
