<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Keys;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis TYPE command.
 *
 * Returns the string representation of the type of the value stored at key.
 * The different types that can be returned are: string, list, set, zset, hash, stream, or none.
 *
 * @see https://redis.io/commands/type/
 *
 * @extends AbstractCommand<string>
 */
final class TypeCommand extends AbstractCommand
{
    public string $id {
        get => 'TYPE';
    }
}
