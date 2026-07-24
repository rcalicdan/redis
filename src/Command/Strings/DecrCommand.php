<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis DECR command.
 *
 * Decrements the number stored at key by one. If the key does not exist,
 * it is set to 0 before performing the operation.
 *
 * @see https://redis.io/commands/decr/
 *
 * @extends AbstractCommand<int>
 */
final class DecrCommand extends AbstractCommand
{
    public string $id {
        get => 'DECR';
    }
}