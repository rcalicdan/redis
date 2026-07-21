<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis DEL command.
 *
 * Removes the specified keys. A key is ignored if it does not exist.
 * Resolves to the integer number of keys that were successfully removed.
 *
 * @see https://redis.io/commands/del/
 * @extends AbstractCommand<int>
 */
final class DelCommand extends AbstractCommand
{
    public string $id {
        get => 'DEL';
    }
}