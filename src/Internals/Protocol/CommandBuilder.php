<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals\Protocol;

use InvalidArgumentException;
use Stringable;

/**
 * @internal
 */
final class CommandBuilder
{
    /**
     * Builds a Redis RESP array from a list of arguments.
     *
     * @param array<int|string, mixed> $args
     */
    public static function build(array $args): string
    {
        $payload = '*' . \count($args) . "\r\n";

        foreach ($args as $arg) {
            if (! \is_scalar($arg) && ! $arg instanceof Stringable && $arg !== null) {
                throw new InvalidArgumentException(
                    'Redis command arguments must be scalar, Stringable, or null. Got: ' . get_debug_type($arg)
                );
            }

            $strArg = (string) $arg;
            $payload .= '$' . \strlen($strArg) . "\r\n" . $strArg . "\r\n";
        }

        return $payload;
    }
}
