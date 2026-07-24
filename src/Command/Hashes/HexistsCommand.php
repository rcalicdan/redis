<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Hashes;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis HEXISTS command.
 *
 * Returns 1 if hash contains field, 0 if hash or field does not exist.
 *
 * @see https://redis.io/commands/hexists/
 *
 * @extends AbstractCommand<int>
 */
final class HexistsCommand extends AbstractCommand
{
    public string $id {
        get => 'HEXISTS';
    }
}
