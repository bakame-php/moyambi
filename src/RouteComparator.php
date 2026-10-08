<?php

declare(strict_types=1);

namespace Bakame\Moyambi;

final class RouteComparator
{
    public static function compare(Route $left, Route $right): int
    {
        $precedence = RoutePrecedence::compare($left, $right);

        return 0 !== $precedence ? $precedence : self::compareMethods($left, $right);
    }

    private static function compareMethods(Route $left, Route $right): int
    {
        return $right->acceptsAnyMethod() <=> $left->acceptsAnyMethod();
    }
}
