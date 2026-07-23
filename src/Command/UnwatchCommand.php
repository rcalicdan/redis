<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis UNWATCH command.
 *
 * Flushes all the previously watched keys for a transaction.
 *
 * @see https://redis.io/commands/unwatch/
 *
 * @extends AbstractCommand<string>
 */
final class UnwatchCommand extends AbstractCommand
{
    public string $id { get => 'UNWATCH'; }
}
