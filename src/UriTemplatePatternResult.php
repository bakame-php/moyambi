<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

use League\Uri\UriTemplate\ExtractionResult;
use League\Uri\UrlPattern\ComponentName;

final readonly class UriTemplatePatternResult
{
    private bool $hasValue;

    private function __construct(
        public ExtractionResult $scheme,
        public ExtractionResult $username,
        public ExtractionResult $password,
        public ExtractionResult $host,
        public ExtractionResult $port,
        public ExtractionResult $path,
        public ExtractionResult $query,
        public ExtractionResult $fragment,
    ) {
        $this->hasValue = $this->scheme->hasValue()
            || $this->username->hasValue()
            || $this->password->hasValue()
            || $this->host->hasValue()
            || $this->port->hasValue()
            || $this->path->hasValue()
            || $this->query->hasValue()
            || $this->fragment->hasValue();
    }

    /**
     * Tells whether the Result contain any variable.
     */
    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    /**
     * @param array<non-empty-string, mixed> $extraction
     */
    public static function tryFrom(array $extraction): ?self
    {
        foreach ($extraction as $name => $value) {
            if (!$value instanceof ExtractionResult || null === ComponentName::tryFrom($name)) {
                return null;
            }
        }

        $empty = ExtractionResult::empty();

        return new self(
            scheme: $extraction[ComponentName::Scheme->value] ?? $empty,
            username: $extraction[ComponentName::Username->value] ?? $empty,
            password: $extraction[ComponentName::Password->value] ?? $empty,
            host: $extraction[ComponentName::Host->value] ?? $empty,
            port: $extraction[ComponentName::Port->value] ?? $empty,
            path: $extraction[ComponentName::Path->value] ?? $empty,
            query: $extraction[ComponentName::Query->value] ?? $empty,
            fragment: $extraction[ComponentName::Fragment->value] ?? $empty,
        );
    }
}
