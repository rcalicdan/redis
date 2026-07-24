<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Transactions;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis WATCH command.
 *
 * Marks the given keys to be watched for conditional execution of a transaction.
 * If any of the watched keys are modified before EXEC, the transaction fails.
 *
 * @see https://redis.io/commands/watch/
 *
 * @extends AbstractCommand<string>
 */
final class WatchCommand extends AbstractCommand
{
    public string $id { get => 'WATCH'; }
}
