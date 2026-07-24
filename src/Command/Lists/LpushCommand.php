<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis LPUSH command.
 *
 * Inserts all specified values at the head of the list stored at key.
 * Resolves to the length of the list after the push operations.
 *
 * @see https://redis.io/commands/lpush/
 *
 * @extends AbstractCommand<int>
 */
final class LpushCommand extends AbstractCommand
{
    public string $id {
        get => 'LPUSH';
    }
}