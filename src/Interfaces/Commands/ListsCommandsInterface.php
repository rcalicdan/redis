<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface ListsCommandsInterface
{
    /**
     * Inserts values at head of list stored at key.
     *
     * @param string $key List key.
     * @param mixed ...$values Values to prepend.
     *
     * @return PromiseInterface<int> Length of list after operation.
     */
    public function lpush(string $key, mixed ...$values): PromiseInterface;

    /**
     * Inserts values at tail of list stored at key.
     *
     * @param string $key List key.
     * @param mixed ...$values Values to append.
     *
     * @return PromiseInterface<int> Length of list after operation.
     */
    public function rpush(string $key, mixed ...$values): PromiseInterface;

    /**
     * Removes and returns first element(s) of list stored at key.
     *
     * @param string $key List key.
     * @param int $count Number of elements to pop.
     *
     * @return PromiseInterface<string|array<int, string>|null>
     */
    public function lpop(string $key, int $count = 1): PromiseInterface;

    /**
     * Removes and returns last element(s) of list stored at key.
     *
     * @param string $key List key.
     * @param int $count Number of elements to pop.
     *
     * @return PromiseInterface<string|array<int, string>|null>
     */
    public function rpop(string $key, int $count = 1): PromiseInterface;

    /**
     * Returns length of list stored at key.
     *
     * @param string $key List key.
     *
     * @return PromiseInterface<int> Length of list.
     */
    public function llen(string $key): PromiseInterface;

    /**
     * Removes and returns first element of a list, blocking connection if empty.
     *
     * @param string|array<string> $keys Target key(s).
     * @param float|int $timeout Maximum block seconds (0 = infinite).
     *
     * @return PromiseInterface<array<int, string>|null> [key, value] or null on timeout.
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface;
}