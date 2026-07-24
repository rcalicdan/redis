<?php

declare(strict_types=1);

namespace Hibla\Redis\Manager;

use Hibla\EventLoop\Loop;
use Hibla\Promise\Exceptions\TimeoutException;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\PoolException;
use Hibla\Redis\Internals\Connection;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Interfaces\ConnectorInterface;
use InvalidArgumentException;
use SplQueue;
use Throwable;

/**
 * @internal
 */
final class PoolManager
{
    /**
     * @var SplQueue<Connection>
     */
    private SplQueue $pool;

    /**
     * @var SplQueue<Promise<Connection>>
     */
    private SplQueue $waiters;

    /**
     * @var array<int, Connection>
     */
    private array $activeConnectionsMap = [];

    /**
     * @var array<int, int>
     */
    private array $connectionLastUsed = [];

    /**
     * @var array<int, int>
     */
    private array $connectionCreatedAt = [];

    private int $activeConnections = 0;

    private bool $isClosing = false;

    private bool $isGracefulShutdown = false;

    private int $idleTimeoutNanos;

    private int $maxLifetimeNanos;

    private PoolException $exhaustedException;

    private readonly RedisConfig $config;

    /**
     * @var Promise<void>|null
     */
    private ?Promise $shutdownPromise = null;

    /**
     * @param RedisConfig|array<string, mixed>|string $config
     */
    public function __construct(
        RedisConfig|array|string $config,
        private readonly int $maxSize = 10,
        private readonly int $minSize = 0,
        int $idleTimeout = 60,
        int $maxLifetime = 3600,
        private readonly int $maxWaiters = 0,
        private readonly float $acquireTimeout = 10.0,
        private readonly ?ConnectorInterface $connector = null
    ) {
        $this->config = match (true) {
            $config instanceof RedisConfig => $config,
            \is_array($config) => RedisConfig::fromArray($config),
            \is_string($config) => RedisConfig::fromUri($config),
        };

        if ($maxSize <= 0) {
            throw new InvalidArgumentException('Pool max size must be greater than 0');
        }

        if ($minSize < 0 || $minSize > $maxSize) {
            throw new InvalidArgumentException('Invalid min size configuration');
        }

        $this->idleTimeoutNanos = $idleTimeout * 1_000_000_000;
        $this->maxLifetimeNanos = $maxLifetime * 1_000_000_000;
        $this->pool = new SplQueue();
        $this->waiters = new SplQueue();

        $this->exhaustedException = new PoolException(
            "Connection pool exhausted. Max waiters limit ({$maxWaiters}) reached."
        );

        $this->ensureMinConnections();
    }

    private int $pendingWaitersCount {
        get {
            $count = 0;
            foreach ($this->waiters as $waiter) {
                if ($waiter->isPending()) {
                    $count++;
                }
            }

            return $count;
        }
    }

    /**
     * @var array<string, bool|float|int>
     */
    public array $stats {
        get {
            return [
                'active_connections' => \count($this->activeConnectionsMap),
                'total_connections' => $this->activeConnections,
                'pooled_connections' => $this->pool->count(),
                'min_size' => $this->minSize,
                'waiting_requests' => $this->pendingWaitersCount,
                'max_size' => $this->maxSize,
                'max_waiters' => $this->maxWaiters,
                'acquire_timeout' => $this->acquireTimeout,
                'is_graceful_shutdown' => $this->isGracefulShutdown,
            ];
        }
    }

    /**
     * @return PromiseInterface<Connection>
     */
    public function get(): PromiseInterface
    {
        if ($this->isClosing || $this->isGracefulShutdown) {
            return Promise::rejected(new PoolException('Pool is shutting down'));
        }

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();

            $connId = spl_object_id($connection);
            $now = (int) hrtime(true);
            $lastUsed = $this->connectionLastUsed[$connId] ?? 0;
            $createdAt = $this->connectionCreatedAt[$connId] ?? 0;

            if (($now - $lastUsed) > $this->idleTimeoutNanos || ($now - $createdAt) > $this->maxLifetimeNanos) {
                $this->removeConnection($connection);

                continue;
            }

            if ($connection->isClosed() || $connection->getState() !== ConnectionState::READY) {
                $this->removeConnection($connection);

                continue;
            }

            unset($this->connectionLastUsed[$connId]);
            $this->activeConnectionsMap[$connId] = $connection;

            return Promise::resolved($connection);
        }

        if ($this->activeConnections < $this->maxSize) {
            return $this->createNewConnection();
        }

        if ($this->maxWaiters > 0 && $this->pendingWaitersCount >= $this->maxWaiters) {
            return Promise::rejected($this->exhaustedException);
        }

        /** @var Promise<Connection> $waiterPromise */
        $waiterPromise = new Promise();

        if ($this->acquireTimeout > 0.0) {
            $timeout = $this->acquireTimeout;
            $timerId = Loop::addTimer($timeout, static function () use ($waiterPromise, $timeout): void {
                if ($waiterPromise->isPending()) {
                    $waiterPromise->reject(new TimeoutException("Acquire timeout of {$timeout}s exceeded"));
                }
            });

            $waiterPromise->finally(static function () use ($timerId): void {
                Loop::cancelTimer($timerId);
            })->catch(static fn () => null);
        }

        $this->waiters->enqueue($waiterPromise);

        return $waiterPromise;
    }

    public function release(Connection $connection): void
    {
        if ($connection->isClosed() || $connection->getState() !== ConnectionState::READY) {
            $this->removeConnection($connection);
            $this->satisfyNextWaiter();

            return;
        }

        $connId = spl_object_id($connection);

        $waiter = $this->dequeueActiveWaiter();

        if ($waiter !== null) {
            $waiter->resolve($connection);

            return;
        }

        if ($this->isGracefulShutdown) {
            unset($this->activeConnectionsMap[$connId]);
            $this->removeConnection($connection);

            return;
        }

        $now = (int) hrtime(true);
        $createdAt = $this->connectionCreatedAt[$connId] ?? 0;

        if (($now - $createdAt) > $this->maxLifetimeNanos) {
            $this->removeConnection($connection);

            return;
        }

        $this->connectionLastUsed[$connId] = $now;
        unset($this->activeConnectionsMap[$connId]);

        $this->pool->enqueue($connection);
    }

    /**
     * @return PromiseInterface<void>
     */
    public function closeAsync(float $timeout = 0.0): PromiseInterface
    {
        if ($this->isClosing) {
            return Promise::resolved();
        }

        if ($this->isGracefulShutdown) {
            return $this->shutdownPromise ?? Promise::resolved();
        }

        $this->isGracefulShutdown = true;

        $shuttingDownException = new PoolException('Pool is shutting down gracefully');
        while (! $this->waiters->isEmpty()) {
            $waiter = $this->waiters->dequeue();
            if ($waiter->isPending()) {
                $waiter->reject($shuttingDownException);
            }
        }

        while (! $this->pool->isEmpty()) {
            $this->removeConnection($this->pool->dequeue(), false);
        }

        /** @var Promise<void> $promise */
        $promise = new Promise();
        $this->shutdownPromise = $promise;

        $this->checkShutdownComplete();

        $activeShutdownPromise = $this->shutdownPromise;

        if ($timeout > 0.0 && $activeShutdownPromise !== null) {
            $timerId = Loop::addTimer($timeout, function (): void {
                if ($this->shutdownPromise !== null && $this->shutdownPromise->isPending()) {
                    $this->close();
                }
            });

            $activeShutdownPromise->finally(static function () use ($timerId): void {
                Loop::cancelTimer($timerId);
            })->catch(static fn () => null);
        }

        return $this->shutdownPromise ?? Promise::resolved();
    }

    public function close(): void
    {
        if ($this->shutdownPromise !== null && $this->shutdownPromise->isPending()) {
            $this->shutdownPromise->resolve(null);
            $this->shutdownPromise = null;
        }

        $this->isGracefulShutdown = false;
        $this->isClosing = true;

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();
            if (! $connection->isClosed()) {
                $connection->close();
            }
        }

        foreach ($this->activeConnectionsMap as $connection) {
            if (! $connection->isClosed()) {
                $connection->close();
            }
        }

        while (! $this->waiters->isEmpty()) {
            $waiter = $this->waiters->dequeue();
            if (! $waiter->isCancelled()) {
                $waiter->reject(new PoolException('Pool closed forcefully'));
            }
        }

        $this->activeConnectionsMap = [];
        $this->connectionLastUsed = [];
        $this->connectionCreatedAt = [];
        $this->activeConnections = 0;
        $this->pool = new SplQueue();
        $this->waiters = new SplQueue();
    }

    /**
     * @return PromiseInterface<array<string, int>>
     */
    public function healthCheck(): PromiseInterface
    {
        /** @var Promise<array<string, int>> $promise */
        $promise = new Promise();

        $stats = [
            'total_checked' => 0,
            'healthy' => 0,
            'unhealthy' => 0,
        ];

        /** @var SplQueue<Connection> $tempQueue */
        $tempQueue = new SplQueue();

        /** @var array<int, PromiseInterface<mixed>> $checkPromises */
        $checkPromises = [];

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();
            $stats['total_checked']++;

            $checkPromises[] = $connection->enqueue(new PingCommand())
                ->then(
                    function () use ($connection, $tempQueue, &$stats): void {
                        $stats['healthy']++;
                        $connId = spl_object_id($connection);
                        $this->connectionLastUsed[$connId] = (int) hrtime(true);
                        $tempQueue->enqueue($connection);
                    },
                    function () use ($connection, &$stats): void {
                        $stats['unhealthy']++;
                        $this->removeConnection($connection);
                    }
                )
            ;
        }

        Promise::all($checkPromises)
            ->then(
                function () use ($promise, $tempQueue, &$stats): void {
                    while (! $tempQueue->isEmpty()) {
                        $conn = $tempQueue->dequeue();
                        if ($this->isClosing || $this->isGracefulShutdown) {
                            $this->removeConnection($conn);
                        } else {
                            $this->pool->enqueue($conn);
                        }
                    }
                    $promise->resolve($stats);
                },
                function (Throwable $e) use ($promise, $tempQueue): void {
                    while (! $tempQueue->isEmpty()) {
                        $conn = $tempQueue->dequeue();
                        if ($this->isClosing || $this->isGracefulShutdown) {
                            $this->removeConnection($conn);
                        } else {
                            $this->pool->enqueue($conn);
                        }
                    }
                    $promise->reject($e);
                }
            )
        ;

        return $promise;
    }

    /**
     * @return Promise<Connection>
     */
    private function createNewConnection(): Promise
    {
        $this->activeConnections++;

        /** @var Promise<Connection> $promise */
        $promise = new Promise();

        $connPromise = Connection::create($this->config, $this->connector);

        $connPromise->then(
            function (Connection $conn) use ($promise): void {
                if ($this->isClosing) {
                    $conn->close();
                    $this->activeConnections--;
                    if (! $promise->isSettled()) {
                        $promise->reject(new PoolException('Pool closed forcefully'));
                    }
                    $this->checkShutdownComplete();

                    return;
                }

                $connId = spl_object_id($conn);
                $this->connectionCreatedAt[$connId] = (int) hrtime(true);
                $this->activeConnectionsMap[$connId] = $conn;

                if ($promise->isCancelled()) {
                    $this->release($conn);

                    return;
                }

                if (! $promise->isSettled()) {
                    $promise->resolve($conn);
                }
            },
            function (Throwable $e) use ($promise): void {
                $this->activeConnections--;
                if (! $promise->isSettled()) {
                    $promise->reject($e);
                }
                $this->checkShutdownComplete();
            }
        );

        $promise->onCancel(function () use ($connPromise): void {
            $this->activeConnections--;

            if (! $connPromise->isSettled()) {
                $connPromise->cancel();
            }

            $this->satisfyNextWaiter();
            $this->checkShutdownComplete();
        });

        return $promise;
    }

    private function satisfyNextWaiter(): void
    {
        if ($this->isClosing || $this->isGracefulShutdown) {
            return;
        }

        if (! $this->waiters->isEmpty() && $this->activeConnections < $this->maxSize) {
            $waiter = $this->dequeueActiveWaiter();
            if ($waiter === null) {
                return;
            }

            $this->activeConnections++;

            $connPromise = Connection::create($this->config, $this->connector);

            $connPromise->then(
                function (Connection $conn) use ($waiter): void {
                    if ($this->isClosing) {
                        $conn->close();
                        $this->activeConnections--;
                        if (! $waiter->isSettled()) {
                            $waiter->reject(new PoolException('Pool closed forcefully'));
                        }
                        $this->checkShutdownComplete();

                        return;
                    }

                    $connId = spl_object_id($conn);
                    $this->connectionCreatedAt[$connId] = (int) hrtime(true);
                    $this->activeConnectionsMap[$connId] = $conn;

                    if ($waiter->isCancelled()) {
                        $this->release($conn);

                        return;
                    }

                    if (! $waiter->isSettled()) {
                        $waiter->resolve($conn);
                    }
                },
                function (Throwable $e) use ($waiter): void {
                    $this->activeConnections--;
                    if (! $waiter->isSettled()) {
                        $waiter->reject($e);
                    }
                    $this->checkShutdownComplete();
                }
            );

            $waiter->onCancel(function () use ($connPromise): void {
                $this->activeConnections--;

                if (! $connPromise->isSettled()) {
                    $connPromise->cancel();
                }

                $this->satisfyNextWaiter();
                $this->checkShutdownComplete();
            });
        }
    }

    private function ensureMinConnections(): void
    {
        if ($this->isClosing || $this->isGracefulShutdown) {
            return;
        }

        while ($this->activeConnections < $this->minSize) {
            $this->createNewConnection()->then(
                function (Connection $conn): void {
                    $waiter = $this->dequeueActiveWaiter();
                    if ($waiter !== null) {
                        $waiter->resolve($conn);
                    } else {
                        if ($this->isClosing || $this->isGracefulShutdown) {
                            $this->removeConnection($conn);

                            return;
                        }

                        $connId = spl_object_id($conn);
                        $this->connectionLastUsed[$connId] = (int) hrtime(true);
                        unset($this->activeConnectionsMap[$connId]);
                        $this->pool->enqueue($conn);
                    }
                },
                fn () => null
            );
        }
    }

    private function removeConnection(Connection $connection, bool $replenish = true): void
    {
        if (! $connection->isClosed()) {
            $connection->close();
        }

        $connId = spl_object_id($connection);
        unset(
            $this->connectionLastUsed[$connId],
            $this->connectionCreatedAt[$connId],
            $this->activeConnectionsMap[$connId]
        );

        $this->activeConnections--;

        if ($replenish && ! $this->isClosing && ! $this->isGracefulShutdown) {
            $this->ensureMinConnections();
        }

        $this->checkShutdownComplete();
    }

    /**
     * @return Promise<Connection>|null
     */
    private function dequeueActiveWaiter(): ?Promise
    {
        while (! $this->waiters->isEmpty()) {
            /** @var Promise<Connection> $waiter */
            $waiter = $this->waiters->dequeue();

            if ($waiter->isPending()) {
                return $waiter;
            }
        }

        return null;
    }

    private function checkShutdownComplete(): void
    {
        if (! $this->isGracefulShutdown) {
            return;
        }

        if ($this->activeConnections > 0) {
            return;
        }

        $this->connectionLastUsed = [];
        $this->connectionCreatedAt = [];

        if ($this->shutdownPromise !== null && $this->shutdownPromise->isPending()) {
            $this->shutdownPromise->resolve(null);
        }

        $this->shutdownPromise = null;
    }
}
