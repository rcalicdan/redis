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
    /** @var SplQueue<Connection> */
    private SplQueue $pool;

    /** @var SplQueue<Promise<Connection>> */
    private SplQueue $waiters;

    private int $activeConnections = 0;
    private bool $isClosing = false;

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
        if ($this->isClosing) {
            return Promise::rejected(new ConnectionException('Pool is shutting down'));
        }

        while (! $this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();

            if ($connection->isClosed()) {
                $this->activeConnections--;
                continue;
            }

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
        if ($connection->isClosed() || $connection->getState() !== ConnectionState::READY) {
            $this->activeConnections--;
            $connection->close();
            $this->satisfyNextWaiter();
            return;
        }

        while (! $this->waiters->isEmpty()) {
            $waiter = $this->waiters->dequeue();
            if ($waiter->isPending()) {
                $waiter->resolve($connection);
                return;
            }
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
                if ($this->isClosing || $promise->isCancelled()) {
                    $conn->close();
                    $this->activeConnections--;
                    $promise->reject(new ConnectionException('Pool closing or request cancelled'));
                    return;
                }
                $promise->resolve($conn);
            },
            function (Throwable $e) use ($promise): void {
                $this->activeConnections--;
                $promise->reject($e);
            }
        );

        return $promise;
    }

    private function satisfyNextWaiter(): void
    {
        if ($this->isClosing || $this->waiters->isEmpty() || $this->activeConnections >= $this->maxSize) {
            return;
        }

        $this->createNewConnection()->then(
            function (Connection $conn): void {
                while (! $this->waiters->isEmpty()) {
                    $waiter = $this->waiters->dequeue();
                    if ($waiter->isPending()) {
                        $waiter->resolve($conn);
                        return;
                    }
                }
                
                $this->pool->enqueue($conn);
            },
            fn() => null
        );
    }

    private function ensureMinConnections(): void
    {
        while ($this->activeConnections < $this->minSize) {
            $this->createNewConnection()->then(
                function (Connection $conn): void {
                    $this->release($conn);
                },
                fn() => null 
            );
        }
    }

    public function close(): void
    {
        $this->isClosing = true;

        while (! $this->pool->isEmpty()) {
            $this->pool->dequeue()->close();
        }

        while (! $this->waiters->isEmpty()) {
            $this->waiters->dequeue()->reject(new ConnectionException('Pool closed'));
        }

        $this->activeConnections = 0;
    }
}