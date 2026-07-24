<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Sets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SREM command.
 *
 * Removes specified members from the set stored at key.
 * Resolves to the number of members that were removed.
 *
 * @see https://redis.io/commands/srem/
 *
 * @extends AbstractCommand<int>
 */
final class SremCommand extends AbstractCommand
{
    public string $id {
        get => 'SREM';
    }
}
