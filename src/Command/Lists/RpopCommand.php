<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis RPOP command.
 *
 * Removes and returns the last element of the list stored at key.
 *
 * @see https://redis.io/commands/rpop/
 *
 * @extends AbstractCommand<string|array<int, string>|null>
 */
final class RpopCommand extends AbstractCommand
{
    public string $id {
        get => 'RPOP';
    }
}
