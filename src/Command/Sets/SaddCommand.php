<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Sets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SADD command.
 *
 * Adds specified members to the set stored at key.
 * Resolves to the number of elements that were added to the set.
 *
 * @see https://redis.io/commands/sadd/
 *
 * @extends AbstractCommand<int>
 */
final class SaddCommand extends AbstractCommand
{
    public string $id {
        get => 'SADD';
    }
}
