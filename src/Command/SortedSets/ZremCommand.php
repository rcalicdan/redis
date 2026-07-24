<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\SortedSets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis ZREM command.
 *
 * Removes specified members from the sorted set stored at key.
 *
 * @see https://redis.io/commands/zrem/
 *
 * @extends AbstractCommand<int>
 */
final class ZremCommand extends AbstractCommand
{
    public string $id {
        get => 'ZREM';
    }
}