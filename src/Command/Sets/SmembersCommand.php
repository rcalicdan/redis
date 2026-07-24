<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Sets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SMEMBERS command.
 *
 * Returns all the members of the set value stored at key.
 *
 * @see https://redis.io/commands/smembers/
 *
 * @extends AbstractCommand<array<int, string>>
 */
final class SmembersCommand extends AbstractCommand
{
    public string $id {
        get => 'SMEMBERS';
    }
}
