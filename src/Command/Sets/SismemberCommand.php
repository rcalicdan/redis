<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Sets;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SISMEMBER command.
 *
 * Returns 1 if member is a member of the set stored at key, 0 otherwise.
 *
 * @see https://redis.io/commands/sismember/
 *
 * @extends AbstractCommand<int>
 */
final class SismemberCommand extends AbstractCommand
{
    public string $id {
        get => 'SISMEMBER';
    }
}
