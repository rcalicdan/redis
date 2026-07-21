<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Contract for standard Redis commands.
 *
 * This interface isolates standard Redis operations from the connection lifecycle,
 * making it easily reusable for features like Transactions (MULTI/EXEC),
 * Pipelining wrappers, or for creating mock objects in unit tests.
 */
interface RedisCommandsInterface
{
    /**
     * Tests the connection to the Redis server.
     *
     * If no message is provided, the server will return "PONG".
     * If a message is provided, the server will echo the exact message back.
     *
     * @param string|null $message Optional message to echo.
     *
     * @return PromiseInterface<string> Resolves to "PONG" or the provided message.
     */
    public function ping(?string $message = null): PromiseInterface;

    /**
     * Retrieves the string value associated with the specified key.
     *
     * If the key does not exist, the promise resolves to null.
     * If the value stored at the key is not a string, the promise will reject
     * with a RedisException.
     *
     * @param string $key The key to retrieve.
     *
     * @return PromiseInterface<string|null> Resolves to the string value, or null if missing.
     */
    public function get(string $key): PromiseInterface;

    /**
     * Sets the specified key to hold the provided string value.
     *
     * If the key already holds a value, it is overwritten regardless of its type.
     * Any previous time-to-live (TTL) associated with the key is discarded on success.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to store (must be scalar or Stringable).
     *
     * @return PromiseInterface<string> Resolves to "OK" on success.
     */
    public function set(string $key, mixed $value): PromiseInterface;

    /**
     * Removes the specified keys.
     *
     * A key is ignored if it does not exist.
     *
     * @param string ...$keys One or more keys to delete.
     *
     * @return PromiseInterface<int> Resolves to the integer number of keys that were successfully removed.
     */
    public function del(string ...$keys): PromiseInterface;

    /**
     * Retrieves the values of all specified keys.
     *
     * For every key that does not hold a string value or does not exist,
     * null is returned in its respective position in the array.
     *
     * @param string ...$keys The keys to retrieve.
     *
     * @return PromiseInterface<array<int, string|null>> Resolves to an array of values matching the order of requested keys.
     */
    public function mget(string ...$keys): PromiseInterface;

    /**
     * Retrieves all fields and values of the hash stored at the specified key.
     *
     * The raw flat array response from Redis is automatically parsed into
     * a PHP associative array. Returns an empty array if the key does not exist.
     *
     * @param string $key The hash key to retrieve.
     *
     * @return PromiseInterface<array<string, string>> Resolves to an associative array of the hash's fields and values.
     */
    public function hgetall(string $key): PromiseInterface;

    /**
     * Removes and returns the first element of a list, blocking the connection if the list is empty.
     *
     * It blocks until another client pushes an element or the timeout is reached.
     * WARNING: This ties up a connection in the pool. If the returned promise is cancelled,
     * the underlying connection will be forcefully closed to prevent protocol desynchronization.
     *
     * @param string|array<string> $keys The list key(s) to pop from.
     * @param float|int $timeout The maximum time to block in seconds (0 means block indefinitely).
     *
     * @return PromiseInterface<array<int, string>|null> Resolves to a 2-element array [key, value], or null on timeout.
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface;
}
