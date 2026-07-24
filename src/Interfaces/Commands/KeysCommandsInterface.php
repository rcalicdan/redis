<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface KeysCommandsInterface
{
    /**
     * Removes the specified keys. A key is ignored if it does not exist.
     *
     * @param string ...$keys One or more keys to delete.
     *
     * @return PromiseInterface<int> Resolves to the number of keys removed.
     */
    public function del(string ...$keys): PromiseInterface;

    /**
     * Returns the number of keys that exist among the requested keys.
     *
     * @param string ...$keys One or more keys to check.
     *
     * @return PromiseInterface<int> Resolves to the count of existing keys.
     */
    public function exists(string ...$keys): PromiseInterface;

    /**
     * Sets a timeout on key in seconds.
     *
     * @param string $key Target key.
     * @param int $seconds Timeout in seconds.
     *
     * @return PromiseInterface<int> Resolves to 1 if timeout was set, 0 if key missing.
     */
    public function expire(string $key, int $seconds): PromiseInterface;

    /**
     * Returns the remaining time to live of a key in seconds.
     *
     * @param string $key Key to inspect.
     *
     * @return PromiseInterface<int> TTL in seconds, -1 if no TTL, -2 if missing.
     */
    public function ttl(string $key): PromiseInterface;

    /**
     * Returns the string representation of the type of value stored at key.
     *
     * @param string $key Key to inspect.
     *
     * @return PromiseInterface<string> Resolves to type string ('string', 'list', 'hash', etc.).
     */
    public function type(string $key): PromiseInterface;

    /**
     * Asynchronously deletes keys in a background thread without blocking the server.
     *
     * @param string ...$keys One or more keys to unlink.
     *
     * @return PromiseInterface<int> Resolves to number of unlinked keys.
     */
    public function unlink(string ...$keys): PromiseInterface;
}
