<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis INCRBYFLOAT command.
 *
 * Increments the string representing a floating point number stored at key by specified increment.
 * Resolves to the string representation of the new value to prevent precision loss.
 *
 * @see https://redis.io/commands/incrbyfloat/
 *
 * @extends AbstractCommand<string>
 */
final class IncrbyfloatCommand extends AbstractCommand
{
    public string $id {
        get => 'INCRBYFLOAT';
    }
}
