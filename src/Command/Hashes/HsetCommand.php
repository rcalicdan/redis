<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Hashes;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis HSET command.
 *
 * Sets the specified fields to their respective values in the hash stored at key.
 * Resolves to the number of fields that were added.
 *
 * @see https://redis.io/commands/hset/
 *
 * @extends AbstractCommand<int>
 */
final class HsetCommand extends AbstractCommand
{
    public string $id {
        get => 'HSET';
    }
}
