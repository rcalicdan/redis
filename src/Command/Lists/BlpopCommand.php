<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Lists;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis BLPOP command (Blocking Left Pop).
 *
 * Removes and returns the first element of a list, blocking the connection
 * if the list is empty until another client pushes an element or the timeout is reached.
 *
 * Note: This is a BLOCKING command. If the user cancels the promise while the connection
 * is waiting in Redis, the client will forcefully close and recreate the underlying socket
 * to prevent desynchronization of the RESP protocol stream.
 *
 * @see https://redis.io/commands/blpop/
 *
 * @extends AbstractCommand<array<int, string>|null>
 */
final class BlpopCommand extends AbstractCommand
{
    public string $id {
        get => 'BLPOP';
    }

    public function isBlocking(): bool
    {
        return true;
    }
}
