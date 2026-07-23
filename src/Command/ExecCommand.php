<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis EXEC command.
 *
 * Executes all previously queued commands in a transaction and restores the
 * connection state to normal.
 *
 * If keys were watched using WATCH, EXEC will only run if none of the watched
 * keys have been modified. If they were modified, EXEC returns null.
 *
 * @see https://redis.io/commands/exec/
 *
 * @extends AbstractCommand<array<int, mixed>|null>
 */
final class ExecCommand extends AbstractCommand
{
    public string $id { get => 'EXEC'; }
}
