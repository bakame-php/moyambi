<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

final readonly class RoutePrecedence
{
    public function __construct(
        public int $value = 0,
    ) {
    }

    public static function compare(Route|self $left, Route|self $right): int
    {
        return self::filter($left)->value <=> self::filter($right)->value;
    }

    private static function filter(Route|self $subject): self
    {
        return $subject instanceof self ? $subject : $subject->precedence;
    }
}
