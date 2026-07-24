<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis INCR command.
 *
 * Increments the number stored at key by one. If the key does not exist,
 * it is set to 0 before performing the operation.
 *
 * @see https://redis.io/commands/incr/
 *
 * @extends AbstractCommand<int>
 */
final class IncrCommand extends AbstractCommand
{
    public string $id {
        get => 'INCR';
    }
}
