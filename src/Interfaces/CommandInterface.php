<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

/**
 * Contract for all Redis commands.
 *
 * Every Redis command encapsulates its own identifier, arguments, parsing rules,
 * and behavior on connection cancellation. By implementing this interface, commands
 * can be plugged cleanly into the async execution pipeline.
 *
 * @template-covariant TResponse The expected PHP return type after parsing.
 */
interface CommandInterface
{
    /**
     * The Redis command ID (e.g., 'GET', 'SET', 'HGETALL').
     */
    public string $id { get; }

    /**
     * The arguments to be sent alongside the command ID.
     *
     * @var array<int|string, mixed>
     */
    public array $arguments { get; }

    /**
     * Indicates if this command blocks the Redis connection (e.g. BLPOP).
     * Used by the connection manager to determine if the socket must be
     * force-closed upon promise cancellation.
     */
    public function isBlocking(): bool;

    /**
     * Parses the raw RESP response from the server into a PHP-friendly format.
     *
     * @return TResponse
     */
    public function parseResponse(mixed $data): mixed;
}
