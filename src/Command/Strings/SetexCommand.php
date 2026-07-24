<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SETEX command.
 *
 * Sets key to hold the string value and set key to timeout after a given number of seconds.
 *
 * @see https://redis.io/commands/setex/
 *
 * @extends AbstractCommand<string>
 */
final class SetexCommand extends AbstractCommand
{
    public string $id {
        get => 'SETEX';
    }
}
