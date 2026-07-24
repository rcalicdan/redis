<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Keys;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis EXISTS command.
 *
 * Returns if key exists. Returns the number of keys that exist
 * among the requested keys.
 *
 * @see https://redis.io/commands/exists/
 *
 * @extends AbstractCommand<int>
 */
final class ExistsCommand extends AbstractCommand
{
    public string $id {
        get => 'EXISTS';
    }
}