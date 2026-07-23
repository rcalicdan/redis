<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis MULTI command.
 *
 * Marks the start of a transaction block. Subsequent commands will be queued
 * for atomic execution using EXEC.
 *
 * @see https://redis.io/commands/multi/
 *
 * @extends AbstractCommand<string>
 */
final class MultiCommand extends AbstractCommand
{
    public string $id { get => 'MULTI'; }
}
