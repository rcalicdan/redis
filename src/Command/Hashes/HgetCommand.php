<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Hashes;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis HGET command.
 *
 * Returns the value associated with field in the hash stored at key.
 * Resolves to null if field or key does not exist.
 *
 * @see https://redis.io/commands/hget/
 *
 * @extends AbstractCommand<string|null>
 */
final class HgetCommand extends AbstractCommand
{
    public string $id {
        get => 'HGET';
    }
}
