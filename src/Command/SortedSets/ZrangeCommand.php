<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\SortedSets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis ZRANGE command.
 *
 * Returns the specified range of elements in the sorted set stored at key.
 *
 * @see https://redis.io/commands/zrange/
 *
 * @extends AbstractCommand<array<int, string>>
 */
final class ZrangeCommand extends AbstractCommand
{
    public string $id {
        get => 'ZRANGE';
    }
}
