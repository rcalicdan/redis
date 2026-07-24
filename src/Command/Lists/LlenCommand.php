<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis LLEN command.
 *
 * Returns the length of the list stored at key.
 *
 * @see https://redis.io/commands/llen/
 *
 * @extends AbstractCommand<int>
 */
final class LlenCommand extends AbstractCommand
{
    public string $id {
        get => 'LLEN';
    }
}