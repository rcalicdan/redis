<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Hashes;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis HMGET command.
 *
 * Returns the values associated with the specified fields in the hash stored at key.
 *
 * @see https://redis.io/commands/hmget/
 *
 * @extends AbstractCommand<array<int, string|null>>
 */
final class HmgetCommand extends AbstractCommand
{
    public string $id {
        get => 'HMGET';
    }
}
