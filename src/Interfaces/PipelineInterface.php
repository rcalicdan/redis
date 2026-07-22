<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

/**
 * Contract for building a Redis pipeline.
 *
 * Pipelines allow multiple commands to be batched and sent to the Redis server
 * in a single TCP write operation, significantly improving throughput.
 */
interface PipelineInterface
{
    /**
     * Adds a PING command to the pipeline.
     *
     * @param string|null $message Optional message to echo.
     *
     * @return self
     */
    public function ping(?string $message = null): self;

    /**
     * Adds a GET command to the pipeline.
     *
     * @param string $key The key to retrieve.
     *
     * @return self
     */
    public function get(string $key): self;

    /**
     * Adds a SET command to the pipeline.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to store (must be scalar or Stringable).
     *
     * @return self
     */
    public function set(string $key, mixed $value): self;

    /**
     * Adds a DEL command to the pipeline.
     *
     * @param string ...$keys One or more keys to delete.
     *
     * @return self
     */
    public function del(string ...$keys): self;

    /**
     * Adds an MGET command to the pipeline.
     *
     * @param string ...$keys The keys to retrieve.
     *
     * @return self
     */
    public function mget(string ...$keys): self;

    /**
     * Adds an HGETALL command to the pipeline.
     *
     * @param string $key The hash key to retrieve.
     *
     * @return self
     */
    public function hgetall(string $key): self;

    /**
     * Adds a BLPOP command to the pipeline.
     *
     * @param string|array<string> $keys The list key(s) to pop from.
     * @param float|int $timeout The maximum time to block in seconds (0 means block indefinitely).
     *
     * @return self
     */
    public function blpop(string|array $keys, float|int $timeout = 0): self;

    /**
     * Adds a PUBLISH command to the pipeline.
     *
     * @param string $channel The channel to broadcast to.
     * @param string $message The message payload.
     *
     * @return self
     */
    public function publish(string $channel, string $message): self;

    /**
     * Adds a custom or raw CommandInterface to the pipeline.
     *
     * @template TResponse
     *
     * @param CommandInterface<TResponse> $command The command to execute.
     *
     * @return self
     */
    public function executeCommand(CommandInterface $command): self;
}
