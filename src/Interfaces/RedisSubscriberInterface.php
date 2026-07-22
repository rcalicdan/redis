<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * A dedicated, stateful connection wrapper for Redis Pub/Sub.
 */
interface RedisSubscriberInterface
{
    /**
     * Subscribes to a channel.
     *
     * @param string $channel The name of the channel.
     * @param callable(string $channel, string $payload): void $callback
     *
     * @return PromiseInterface<void>
     */
    public function subscribe(string $channel, callable $callback): PromiseInterface;

    /**
     * Unsubscribes from a channel.
     * If a callback is provided, only that specific callback is removed.
     * If no callback is provided, all callbacks for the channel are removed.
     *
     * @param string $channel The name of the channel.
     * @param (callable(string $channel, string $payload): void)|null $callback Optional specific callback to remove.
     *
     * @return PromiseInterface<void>
     */
    public function unsubscribe(string $channel, ?callable $callback = null): PromiseInterface;

    /**
     * Subscribes to channels matching a pattern.
     *
     * @param string $pattern The pattern to match (e.g., 'news.*').
     * @param callable(string $pattern, string $channel, string $payload): void $callback
     *
     * @return PromiseInterface<void>
     */
    public function psubscribe(string $pattern, callable $callback): PromiseInterface;

    /**
     * Unsubscribes from a pattern.
     * If a callback is provided, only that specific callback is removed.
     * If no callback is provided, all callbacks for the pattern are removed.
     *
     * @param string $pattern The pattern to unsubscribe from.
     * @param (callable(string $pattern, string $channel, string $payload): void)|null $callback Optional specific callback to remove.
     *
     * @return PromiseInterface<void>
     */
    public function punsubscribe(string $pattern, ?callable $callback = null): PromiseInterface;

    /**
     * Closes the subscriber connection immediately.
     *
     * @return PromiseInterface<void>
     */
    public function close(): PromiseInterface;
}
