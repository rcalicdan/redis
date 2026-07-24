<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Keys;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis UNLINK command.
 *
 * Asynchronously deletes keys in a background thread. Similar to DEL,
 * but non-blocking for large keys.
 *
 * @see https://redis.io/commands/unlink/
 *
 * @extends AbstractCommand<int>
 */
final class UnlinkCommand extends AbstractCommand
{
    public string $id {
        get => 'UNLINK';
    }
}
