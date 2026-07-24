<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis RPUSH command.
 *
 * Inserts all specified values at the tail of the list stored at key.
 * Resolves to the length of the list after the push operations.
 *
 * @see https://redis.io/commands/rpush/
 *
 * @extends AbstractCommand<int>
 */
final class RpushCommand extends AbstractCommand
{
    public string $id {
        get => 'RPUSH';
    }
}