<?php

declare(strict_types=1);

namespace Hibla\Redis;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\BlpopCommand;
use Hibla\Redis\Command\DelCommand;
use Hibla\Redis\Command\GetCommand;
use Hibla\Redis\Command\HgetallCommand;
use Hibla\Redis\Command\MgetCommand;
use Hibla\Redis\Command\PingCommand;
use Hibla\Redis\Command\SetCommand;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Internals\Connection;
use Hibla\Redis\Manager\PoolManager;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Interfaces\ConnectorInterface;

final class RedisClient
{
    private ?PoolManager $pool = null;

    public function __construct(
        RedisConfig|array|string $config,
        int $minConnections = 1,
        int $maxConnections = 10,
        ?ConnectorInterface $connector = null
    ) {
        $redisConfig = match (true) {
            $config instanceof RedisConfig => $config,
            \is_array($config) => RedisConfig::fromArray($config),
            \is_string($config) => RedisConfig::fromUri($config),
        };

        $this->pool = new PoolManager($redisConfig, $maxConnections, $minConnections, $connector);
    }

    /**
     * @return PromiseInterface<mixed>
     */
    public function executeCommand(CommandInterface $command): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pool = $this->pool;
        $connection = null;

        $promise = $pool->get()
            ->then(function (Connection $conn) use ($command, &$connection): PromiseInterface {
                $connection = $conn;
                return $conn->enqueue($command);
            })
            ->finally(function () use ($pool, &$connection): void {
                if ($connection !== null) {
                    $pool->release($connection);
                }
            });

        return Promise::propagateCancellation($promise);
    }

    /**
     * Ping the server.
     * @return PromiseInterface<string> Resolves with "PONG" or the provided message.
     */
    public function ping(?string $message = null): PromiseInterface
    {
        $args = $message === null ? [] : [$message];
        return $this->executeCommand(new PingCommand($args));
    }

    /**
     * Get the value of a key.
     * @return PromiseInterface<string|null>
     */
    public function get(string $key): PromiseInterface
    {
        return $this->executeCommand(new GetCommand([$key]));
    }

    /**
     * Set the string value of a key.
     * @return PromiseInterface<string|null> "OK" on success.
     */
    public function set(string $key, mixed $value): PromiseInterface
    {
        return $this->executeCommand(new SetCommand([$key, $value]));
    }

    /**
     * Delete one or more keys.
     * @return PromiseInterface<int> The number of keys that were removed.
     */
    public function del(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new DelCommand($keys));
    }

    /**
     * Get the values of all the given keys.
     * @return PromiseInterface<array<int, string|null>>
     */
    public function mget(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new MgetCommand($keys));
    }

    /**
     * Get all the fields and values in a hash.
     * @return PromiseInterface<array<string, string>> Associative array of field => value
     */
    public function hgetall(string $key): PromiseInterface
    {
        return $this->executeCommand(new HgetallCommand([$key]));
    }

    /**
     * Blocks the connection until an element is popped from the list.
     * Use Promise::timeout() to wrap this if you don't want to wait forever.
     *
     * @param string|array<string> $keys
     * @param float|int $timeout 0 for no timeout
     * @return PromiseInterface<array<int, string>|null>
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface
    {
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;

        return $this->executeCommand(new BlpopCommand($args));
    }

    public function close(): void
    {
        $this->pool?->close();
        $this->pool = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}