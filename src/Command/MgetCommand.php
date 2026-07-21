<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis MGET command (Multiple Get).
 *
 * Returns the values of all specified keys. For every key that does not hold
 * a string value or does not exist, null is returned in its respective position.
 *
 * @see https://redis.io/commands/mget/
 * @extends AbstractCommand<array<int, string|null>>
 */
final class MgetCommand extends AbstractCommand
{
    public string $id {
        get => 'MGET';
    }
}