<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\SortedSets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis ZADD command.
 *
 * Adds all specified members with the specified scores to the sorted set stored at key.
 *
 * @see https://redis.io/commands/zadd/
 *
 * @extends AbstractCommand<int>
 */
final class ZaddCommand extends AbstractCommand
{
    public string $id {
        get => 'ZADD';
    }
}