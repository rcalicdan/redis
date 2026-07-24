<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\SortedSets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis ZSCORE command.
 *
 * Returns the score of member in the sorted set at key.
 *
 * @see https://redis.io/commands/zscore/
 *
 * @extends AbstractCommand<string|null>
 */
final class ZscoreCommand extends AbstractCommand
{
    public string $id {
        get => 'ZSCORE';
    }
}