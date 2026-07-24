<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface SetsCommandsInterface
{
    /**
     * Adds members to set stored at key.
     *
     * @param string $key Set key.
     * @param mixed ...$members Members to add.
     *
     * @return PromiseInterface<int> Number of elements added.
     */
    public function sadd(string $key, mixed ...$members): PromiseInterface;

    /**
     * Removes members from set stored at key.
     *
     * @param string $key Set key.
     * @param mixed ...$members Members to remove.
     *
     * @return PromiseInterface<int> Number of elements removed.
     */
    public function srem(string $key, mixed ...$members): PromiseInterface;

    /**
     * Returns all members of set stored at key.
     *
     * @param string $key Set key.
     *
     * @return PromiseInterface<array<int, string>> Array of all set members.
     */
    public function smembers(string $key): PromiseInterface;

    /**
     * Returns if member belongs to set stored at key.
     *
     * @param string $key Set key.
     * @param mixed $member Member to test.
     *
     * @return PromiseInterface<int> 1 if member, 0 otherwise.
     */
    public function sismember(string $key, mixed $member): PromiseInterface;
}