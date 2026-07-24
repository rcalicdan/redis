<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Hashes;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis HDEL command.
 *
 * Removes the specified fields from the hash stored at key.
 * Resolves to the number of fields that were removed.
 *
 * @see https://redis.io/commands/hdel/
 *
 * @extends AbstractCommand<int>
 */
final class HdelCommand extends AbstractCommand
{
    public string $id {
        get => 'HDEL';
    }
}