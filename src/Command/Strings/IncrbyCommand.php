<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis INCRBY command.
 *
 * Increments the number stored at key by increment amount.
 *
 * @see https://redis.io/commands/incrby/
 *
 * @extends AbstractCommand<int>
 */
final class IncrbyCommand extends AbstractCommand
{
    public string $id {
        get => 'INCRBY';
    }
}