<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Keys;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis TTL command.
 *
 * Returns the remaining time to live of a key that has an expire set, in seconds.
 * Returns -1 if the key exists but has no associated expire.
 * Returns -2 if the key does not exist.
 *
 * @see https://redis.io/commands/ttl/
 *
 * @extends AbstractCommand<int>
 */
final class TtlCommand extends AbstractCommand
{
    public string $id {
        get => 'TTL';
    }
}
