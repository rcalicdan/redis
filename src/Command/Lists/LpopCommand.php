<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis LPOP command.
 *
 * Removes and returns the first element of the list stored at key.
 *
 * @see https://redis.io/commands/lpop/
 *
 * @extends AbstractCommand<string|array<int, string>|null>
 */
final class LpopCommand extends AbstractCommand
{
    public string $id {
        get => 'LPOP';
    }
}
