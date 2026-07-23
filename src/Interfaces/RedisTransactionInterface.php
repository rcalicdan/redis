<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * A dedicated, stateful connection wrapper for executing Redis transactions.
 * Includes support for Optimistic Locking via WATCH/UNWATCH.
 */
interface RedisTransactionInterface extends RedisCommandsInterface
{
    /**
     * Marks the given keys to be watched for conditional execution of a transaction.
     *
     * @param string ...$keys The keys to watch.
     *
     * @return PromiseInterface<string> Resolves to "OK"
     */
    public function watch(string ...$keys): PromiseInterface;

    /**
     * Flushes all the previously watched keys for a transaction.
     *
     * @return PromiseInterface<string> Resolves to "OK"
     */
    public function unwatch(): PromiseInterface;

    /**
     * Marks the start of a transaction block. Subsequent commands will be queued.
     *
     * @return PromiseInterface<string> Resolves to "OK"
     */
    public function multi(): PromiseInterface;

    /**
     * Executes all previously queued commands in a transaction.
     *
     * @return PromiseInterface<array<int, mixed>|null> Resolves to an array of command replies, or null if watched keys were modified.
     */
    public function exec(): PromiseInterface;

    /**
     * Flushes all previously queued commands in a transaction and restores the connection state to normal.
     *
     * @return PromiseInterface<string> Resolves to "OK"
     */
    public function discard(): PromiseInterface;

    /**
     * Executes a raw command directly on the transaction's dedicated connection.
     *
     * @template TReturn
     *
     * @param CommandInterface<TReturn> $command
     *
     * @return PromiseInterface<TReturn>
     */
    public function executeCommand(CommandInterface $command): PromiseInterface;
}
