<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Keys;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis EXPIRE command.
 *
 * Sets a timeout on key. After the timeout has expired, the key will
 * automatically be deleted. Resolves to 1 if the timeout was set, or 0 if
 * the key does not exist.
 *
 * @see https://redis.io/commands/expire/
 *
 * @extends AbstractCommand<int>
 */
final class ExpireCommand extends AbstractCommand
{
    public string $id {
        get => 'EXPIRE';
    }
}
