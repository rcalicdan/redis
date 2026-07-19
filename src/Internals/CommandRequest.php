<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Promise\Promise;
use Hibla\Redis\Interfaces\CommandInterface;

/**
 * Represents a queued command to be executed on the Redis connection.
 *
 * @internal
 */
final readonly class CommandRequest
{
    /**
     * @param Promise<mixed> $promise The promise to resolve/reject when the command completes.
     * @param CommandInterface $command The Redis command to execute and parse.
     */
    public function __construct(
        public Promise $promise,
        public CommandInterface $command
    ) {
    }
}
