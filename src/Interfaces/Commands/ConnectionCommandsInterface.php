<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface ConnectionCommandsInterface
{
    /**
     * Tests the connection to the Redis server.
     *
     * @param string|null $message Optional message to echo.
     *
     * @return PromiseInterface<string> Resolves to "PONG" or the provided message.
     */
    public function ping(?string $message = null): PromiseInterface;
}