<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis DISCARD command.
 *
 * Flushes all previously queued commands in a transaction and restores the
 * connection state to normal. If WATCH was used, DISCARD unwatches all keys.
 *
 * @see https://redis.io/commands/discard/
 *
 * @extends AbstractCommand<string>
 */
final class DiscardCommand extends AbstractCommand
{
    public string $id { get => 'DISCARD'; }
}
