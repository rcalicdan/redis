<?php

declare(strict_types=1);

namespace Hibla\Redis\Manager;

use Hibla\EventLoop\Loop;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Internals\Connection;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Interfaces\ConnectorInterface;
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

    private int $activeConnections = 0;

    private bool $isClosing = false;

    private bool $isGracefulShutdown = false;

    /**
     * @var Promise<void>|null
     */
    private ?Promise $shutdownPromise = null;

    public function __construct(
        private readonly RedisConfig $config,
        private readonly int $maxSize = 10,
        private readonly int $minSize = 1,
        private readonly ?ConnectorInterface $connector = null
    ) {
        $this->pool = new SplQueue();
        $this->waiters = new SplQueue();
        $this->ensureMinConnections();
    }

    /**
     * @return PromiseInterface<Connection>
     */
    public function get(): PromiseInterface
    {
        if ($this->isClosing || $this->isGracefulShutdown) {
            return Promise::rejected(new ConnectionException('Pool is shutting down'));
        }

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();

            if ($connection->isClosed()) {
                $this->activeConnections--;

                continue;
            }

            $connId = spl_object_id($connection);
            $this->activeConnectionsMap[$connId] = $connection;

            return Promise::resolved($connection);
        }

        if ($this->activeConnections < $this->maxSize) {
            return $this->createNewConnection();
        }

        /** @var Promise<Connection> $waiter */
        $waiter = new Promise();
        $this->waiters->enqueue($waiter);

        return $waiter;
    }

    public function release(Connection $connection): void
    {
        $connId = spl_object_id($connection);
        unset($this->activeConnectionsMap[$connId]);

        if ($connection->isClosed() || $connection->getState() !== ConnectionState::READY) {
            $this->activeConnections--;
            $connection->close();
            $this->satisfyNextWaiter();
            $this->checkShutdownComplete();

            return;
        }

        while (! $this->waiters->isEmpty()) {
            $waiter = $this->waiters->dequeue();
            if ($waiter->isPending()) {
                $this->activeConnectionsMap[$connId] = $connection;
                $waiter->resolve($connection);

                return;
            }
        }

        if ($this->isGracefulShutdown) {
            $this->activeConnections--;
            $connection->close();
            $this->checkShutdownComplete();

            return;
        }

        $this->pool->enqueue($connection);
    }

    /**
     * @return Promise<Connection>
     */
    private function createNewConnection(): Promise
    {
        $this->activeConnections++;

        /** @var Promise<Connection> $promise */
        $promise = new Promise();

        Connection::create($this->config, $this->connector)->then(
            function (Connection $conn) use ($promise): void {
                if ($this->isClosing || $this->isGracefulShutdown || $promise->isCancelled()) {
                    $conn->close();
                    $this->activeConnections--;
                    $promise->reject(new ConnectionException('Pool closing or request cancelled'));
                    $this->checkShutdownComplete();

                    return;
                }

                $connId = spl_object_id($conn);
                $this->activeConnectionsMap[$connId] = $conn;

                $promise->resolve($conn);
            },
            function (Throwable $e) use ($promise): void {
                $this->activeConnections--;
                $promise->reject($e);
                $this->checkShutdownComplete();
            }
        );

        return $promise;
    }

    public function closeAsync(float $timeout = 0.0): PromiseInterface
    {
        if ($this->isClosing) {
            return Promise::resolved();
        }

        if ($this->isGracefulShutdown) {
            return $this->shutdownPromise ?? Promise::resolved();
        }

        $this->isGracefulShutdown = true;

        $shuttingDownException = new ConnectionException('Pool is shutting down gracefully');
        while (! $this->waiters->isEmpty()) {
            $waiter = $this->waiters->dequeue();
            if ($waiter->isPending()) {
                $waiter->reject($shuttingDownException);
            }
        }

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();
            if (! $connection->isClosed()) {
                $connection->close();
            }
            $this->activeConnections--;
        }

        $this->shutdownPromise = new Promise();
        $this->checkShutdownComplete();

        if ($timeout > 0.0 && $this->shutdownPromise !== null) {
            $timerId = Loop::addTimer($timeout, function (): void {
                if ($this->shutdownPromise !== null && $this->shutdownPromise->isPending()) {
                    $this->close();
                }
            });

            $this->shutdownPromise->finally(static function () use ($timerId): void {
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
            $this->pool->dequeue()->close();
        }

        foreach ($this->activeConnectionsMap as $connection) {
            if (! $connection->isClosed()) {
                $connection->close();
            }
        }
        $this->activeConnectionsMap = [];

        while (! $this->waiters->isEmpty()) {
            $this->waiters->dequeue()->reject(new ConnectionException('Pool closed forcefully'));
        }

        $this->activeConnections = 0;
    }

    private function satisfyNextWaiter(): void
    {
        if ($this->isClosing || $this->isGracefulShutdown || $this->waiters->isEmpty() || $this->activeConnections >= $this->maxSize) {
            return;
        }

        $this->createNewConnection()->then(
            function (Connection $conn): void {
                $connId = spl_object_id($conn);

                while (! $this->waiters->isEmpty()) {
                    $waiter = $this->waiters->dequeue();
                    if ($waiter->isPending()) {
                        $this->activeConnectionsMap[$connId] = $conn;
                        $waiter->resolve($conn);

                        return;
                    }
                }

                unset($this->activeConnectionsMap[$connId]);
                $this->pool->enqueue($conn);
            },
            fn () => null
        );
    }

    private function ensureMinConnections(): void
    {
        while ($this->activeConnections < $this->minSize) {
            $this->createNewConnection()->then(
                function (Connection $conn): void {
                    $this->release($conn);
                },
                fn () => null
            );
        }
    }

    private function checkShutdownComplete(): void
    {
        if (! $this->isGracefulShutdown) {
            return;
        }

        if ($this->activeConnections > 0) {
            return;
        }

        if ($this->shutdownPromise !== null && $this->shutdownPromise->isPending()) {
            $this->shutdownPromise->resolve(null);
        }

        $this->shutdownPromise = null;
    }
}
