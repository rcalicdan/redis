<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Contract for the primary Redis client.
 *
 * This extends standard Redis commands to also include connection pool lifecycle
 * management, health checking, and low-level command execution.
 */
interface RedisClientInterface extends RedisCommandsInterface
{
    /**
     * Returns near real-time statistics about the internal connection pool.
     *
     * Useful metrics include:
     * - `active_connections`: Connections currently processing commands.
     * - `pooled_connections`: Idle connections ready to be used.
     * - `waiting_requests`: Commands queued because max connections are reached.
     *
     * @var array<string, bool|float|int>
     */
    public array $stats { get; }

    /**
     * Executes any custom or raw Redis command not directly mapped as a method.
     *
     * Acquires a connection from the pool, formats the command payload,
     * sends it to the server, and parses the response according to the provided command object.
     *
     * @template TReturn
     *
     * @param CommandInterface<TReturn> $command The command definition and parser.
     *
     * @return PromiseInterface<TReturn> Resolves to the parsed response type of the given command.
     */
    public function executeCommand(CommandInterface $command): PromiseInterface;

    /**
     * Actively tests the health of all currently idle connections in the pool.
     *
     * It sends a PING command to every idle connection. Connections that fail
     * are automatically purged from the pool.
     *
     * @return PromiseInterface<array<string, int>> Resolves to an array containing `total_checked`, `healthy`, and `unhealthy` counts.
     */
    public function healthCheck(): PromiseInterface;

    /**
     * Executes multiple commands in a single TCP write for maximum throughput.
     *
     * @param callable(PipelineInterface): void $callback Function that builds the pipeline.
     *
     * @return PromiseInterface<array<int, mixed>> Resolves to an array of results matching the order the commands were queued.
     */
    public function pipeline(callable $callback): PromiseInterface;

    /**
     * Posts a message to the given channel.
     *
     * @param string $channel The channel to broadcast to.
     * @param string $message The message payload.
     *
     * @return PromiseInterface<int> Resolves to the number of clients that received the message.
     */
    public function publish(string $channel, string $message): PromiseInterface;

    /**
     * Creates a dedicated, unpooled RedisSubscriber for Pub/Sub.
     *
     * This creates a standalone TCP connection to Redis that is completely
     * isolated from the connection pool with transparent auto-reconnection.
     *
     * @param float $minReconnectInterval The initial wait time in seconds before reconnecting.
     * @param float $maxReconnectInterval The maximum wait time in seconds for exponential backoff.
     *
     * @return PromiseInterface<RedisSubscriberInterface>
     */
    public function createSubscriber(float $minReconnectInterval = 1.0, float $maxReconnectInterval = 30.0): PromiseInterface;

    /**
     * Initiates a graceful shutdown of the connection pool.
     *
     * Rejects any new commands, but allows currently active commands to finish
     * up to the specified timeout. Once all pending commands finish (or timeout hits),
     * connections are cleanly closed.
     *
     * @param float $timeout The maximum time in seconds to wait for active commands to finish before forcefully closing.
     *
     * @return PromiseInterface<void> Resolves when the pool has been completely shut down.
     */
    public function closeAsync(float $timeout = 0.0): PromiseInterface;

    /**
     * Immediately and forcefully closes all connections in the pool.
     *
     * Any commands currently in-flight or waiting in the queue will have their
     * promises rejected with a PoolException.
     */
    public function close(): void;
}
