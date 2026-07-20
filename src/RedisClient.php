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

    private bool $isClosing = false;

    private ?PromiseInterface $closePromise = null;

    public function __construct(
        RedisConfig|array|string $config,
        int $minConnections = 0,
        int $maxConnections = 10,
        int $idleTimeout = 60,
        int $maxLifetime = 3600,
        int $maxWaiters = 0,
        float $acquireTimeout = 10.0,
        ?ConnectorInterface $connector = null
    ) {
        $redisConfig = match (true) {
            $config instanceof RedisConfig => $config,
            \is_array($config) => RedisConfig::fromArray($config),
            \is_string($config) => RedisConfig::fromUri($config),
        };

        $this->pool = new PoolManager(
            config: $redisConfig,
            maxSize: $maxConnections,
            minSize: $minConnections,
            idleTimeout: $idleTimeout,
            maxLifetime: $maxLifetime,
            maxWaiters: $maxWaiters,
            acquireTimeout: $acquireTimeout,
            connector: $connector
        );
    }

    /**
     * @var array<string, bool|float|int>
     */
    public array $stats {
        get {
            if ($this->pool === null) {
                return [];
            }

            return $this->pool->stats;
        }
    }

    /**
     * Executes any CommandInterface.
     *
     * @return PromiseInterface<mixed>
     */
    public function executeCommand(CommandInterface $command): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pool = $this->pool;
        $connection = null;
        $innerPromise = null;

        /** @var Promise<mixed> $outerPromise */
        $outerPromise = new Promise();

        $poolPromise = $pool->get();

        $poolPromise->then(function (Connection $conn) use ($command, &$connection, &$innerPromise, $outerPromise, $pool): void {
            $connection = $conn;

            if ($outerPromise->isCancelled()) {
                $pool->release($connection);

                return;
            }

            $innerPromise = $conn->enqueue($command);

            $innerPromise->then(
                function (mixed $result) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->resolve($result);
                    }
                },
                function (\Throwable $e) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->reject($e);
                    }
                }
            );

            $outerPromise->onCancel(function () use ($innerPromise): void {
                if (! $innerPromise->isSettled()) {
                    $innerPromise->cancel();
                }
            });

        }, function (\Throwable $e) use ($outerPromise): void {
            if (! $outerPromise->isSettled()) {
                $outerPromise->reject($e);
            }
        });

        $outerPromise->finally(function () use ($pool, &$connection): void {
            if ($connection !== null) {
                $pool->release($connection);
            }
        });

        $outerPromise->onCancel(function () use ($poolPromise): void {
            if (! $poolPromise->isSettled()) {
                $poolPromise->cancel();
            }
        });

        return $outerPromise;
    }

    /**
     * @return PromiseInterface<array<string, int>>
     */
    public function healthCheck(): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        return $this->pool->healthCheck();
    }

    /**
     * @return PromiseInterface<string> "PONG"
     */
    public function ping(?string $message = null): PromiseInterface
    {
        $args = $message === null ? [] : [$message];

        return $this->executeCommand(new PingCommand($args));
    }

    /**
     * Get the value of a key.
     *
     * @return PromiseInterface<string|null>
     */
    public function get(string $key): PromiseInterface
    {
        return $this->executeCommand(new GetCommand([$key]));
    }

    /**
     * @return PromiseInterface<string> "OK" on success
     */
    public function set(string $key, mixed $value): PromiseInterface
    {
        return $this->executeCommand(new SetCommand([$key, $value]));
    }

    /**
     * @return PromiseInterface<int> Number of deleted keys
     */
    public function del(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new DelCommand($keys));
    }

    /**
     * Get the values of all the given keys.
     *
     * @return PromiseInterface<array<int, string|null>>
     */
    public function mget(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new MgetCommand($keys));
    }

    /**
     * @return PromiseInterface<array<string, string>>
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
     *
     * @return PromiseInterface<array<int, string>|null>
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface
    {
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;

        return $this->executeCommand(new BlpopCommand($args));
    }

    /**
     * @return PromiseInterface<void>
     */
    public function closeAsync(float $timeout = 0.0): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::resolved();
        }

        if ($this->closePromise !== null) {
            return $this->closePromise;
        }

        $pool = $this->pool;

        $this->closePromise = $pool->closeAsync($timeout)
            ->then(function (): void {
                if ($this->isClosing) {
                    return;
                }

                $this->pool = null;
                $this->closePromise = null;
            })
        ;

        return $this->closePromise;
    }

    public function close(): void
    {
        if ($this->pool === null) {
            return;
        }

        $this->isClosing = true;

        $this->pool->close();
        $this->pool = null;
        $this->closePromise = null;

        $this->isClosing = false;
    }

    public function __destruct()
    {
        $this->close();
    }
}
