<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis PING command.
 *
 * Primarily used to test if a connection is still alive, or to measure latency.
 * If no argument is provided, the server returns "PONG". If a custom message is sent,
 * the server echoes that exact message back.
 *
 * @see https://redis.io/commands/ping/
 *
 * @extends AbstractCommand<string>
 */
final class PingCommand extends AbstractCommand
{
    public string $id {
        get => 'PING';
    }
}
