<?php

declare(strict_types=1);

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Exceptions\PoolException;
use Hibla\Redis\Internals\Connection;
use Hibla\Redis\Manager\PoolManager;

use function Hibla\await;
use function Hibla\delay;

describe('Pool Waiter Cancellation', function (): void {

    it('throws CancelledException when awaiting a cancelled waiter', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $waiter = $pool->get();
            $waiter->cancel();

            expect(fn () => await($waiter))
                ->toThrow(CancelledException::class)
            ;

            $pool->release($conn);
        } finally {
            $pool->close();
        }
    });

    it('does not decrement active connections when a waiter is cancelled', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            expect($pool->stats['active_connections'])->toBe(1);

            $waiter = $pool->get();
            $waiter->cancel();

            expect($pool->stats['active_connections'])->toBe(1);

            $pool->release($conn);
        } finally {
            $pool->close();
        }
    });

    it('skips a cancelled waiter and resolves the next active waiter on release', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $cancelledWaiter = $pool->get();
            $activeWaiter = $pool->get();

            expect($pool->stats['waiting_requests'])->toBe(2);

            $cancelledWaiter->cancel();

            expect($pool->stats['waiting_requests'])->toBe(1);

            $pool->release($conn);

            $resolvedConn = await($activeWaiter);

            await(delay(0.01));

            expect($resolvedConn)->toBeInstanceOf(Connection::class)
                ->and($resolvedConn->isReady())->toBeTrue()
                ->and($pool->stats['waiting_requests'])->toBe(0)
            ;

            $pool->release($resolvedConn);
        } finally {
            $pool->close();
        }
    });

    it('returns the connection to the pool when released after skipping a cancelled waiter', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $waiter = $pool->get();
            $waiter->cancel();

            $pool->release($conn);

            expect($pool->stats['pooled_connections'])->toBe(1);
        } finally {
            $pool->close();
        }
    });

    it('handles multiple cancelled waiters and resolves the first active one', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $w1 = $pool->get();
            $w2 = $pool->get();
            $w3 = $pool->get();

            $w1->cancel();
            $w2->cancel();

            $pool->release($conn);

            $resolvedConn = await($w3);

            expect($resolvedConn)->toBeInstanceOf(Connection::class)
                ->and($resolvedConn->isReady())->toBeTrue()
            ;

            $pool->release($resolvedConn);
        } finally {
            $pool->close();
        }
    });
});

describe('Pool Query Cancellation Integration', function (): void {

    it('keeps connection alive and pooled after cancelling a non-blocking command', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $query = $conn->enqueue(new PingCommand(['IGNORED']));
            $query->cancel();

            expect(fn () => await($query))->toThrow(CancelledException::class);

            $pool->release($conn);
            await(delay(0.01));

            expect($pool->stats['pooled_connections'])->toBe(1)
                ->and($pool->stats['active_connections'])->toBe(0)
            ;
        } finally {
            $pool->close();
        }
    });

    it('drops connection and replenishes when cancelling a blocking command', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1, minSize: 1);

        try {
            $conn = await($pool->get());

            $query = $conn->enqueue(new BlpopCommand(['empty_list', 0]));
            await(delay(0.05)); // ensure it hits socket
            $query->cancel();

            expect(fn () => await($query))->toThrow(CancelledException::class);

            $pool->release($conn);

            // Wait for replenishment
            $attempts = 0;
            while ($pool->stats['pooled_connections'] < 1 && $attempts < 40) {
                await(delay(0.05));
                $attempts++;
            }

            expect($pool->stats['pooled_connections'])->toBe(1)
                ->and($pool->stats['active_connections'])->toBe(0)
            ;
        } finally {
            $pool->close();
        }
    });

    it('pool remains fully operational after multiple cancellations across connections', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 3);

        try {
            for ($i = 0; $i < 3; $i++) {
                $conn = await($pool->get());
                $queryPromise = $conn->enqueue(new PingCommand(['IGNORE_ME']));

                $queryPromise->cancel();

                expect(fn () => await($queryPromise))
                    ->toThrow(CancelledException::class)
                ;

                $pool->release($conn);
            }

            await(delay(0.05));

            $conn = await($pool->get());
            $response = await($conn->enqueue(new PingCommand(['ALL_GREEN'])));

            expect($response)->toBe('ALL_GREEN');

            $pool->release($conn);
        } finally {
            $pool->close();
        }
    });
});

describe('Pool Graceful Shutdown Cancellation', function (): void {
    it('rejects pending waiters immediately when pool is closed gracefully', function () {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $waiter = $pool->get();
            $shutdown = $pool->closeAsync();

            expect(fn () => await($waiter))->toThrow(PoolException::class, 'Pool is shutting down gracefully');

            $pool->release($conn);
            await($shutdown);
        } finally {
            $pool->close();
        }
    });
});

describe('Shutdown and Cancellation Edge Cases', function (): void {

    it('completes graceful shutdown when an active blocking command is cancelled', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $query = $conn->enqueue(new BlpopCommand(['empty_list', 0]));
            await(delay(0.01));

            $shutdown = $pool->closeAsync();

            expect($shutdown->isPending())->toBeTrue();

            $query->cancel();

            $pool->release($conn);
            await($shutdown);

            expect($pool->stats['active_connections'])->toBe(0)
                ->and($pool->stats['pooled_connections'])->toBe(0)
            ;
        } finally {
            $pool->close();
        }
    });

    it('completes graceful shutdown when an active non-blocking command is cancelled', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $query = $conn->enqueue(new PingCommand(['IGNORE']));

            $shutdown = $pool->closeAsync();

            $query->cancel();
            $pool->release($conn);

            await($shutdown);

            expect($pool->stats['active_connections'])->toBe(0)
                ->and($pool->stats['pooled_connections'])->toBe(0)
            ;
        } finally {
            $pool->close();
        }
    });

    it('safely handles waiter cancellation after graceful shutdown has already rejected it', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $waiter = $pool->get();

            $shutdown = $pool->closeAsync();

            $waiter->cancel();

            $pool->release($conn);
            await($shutdown);

            expect($waiter->isRejected())->toBeTrue();

            try {
                await($waiter);
                $this->fail('Expected exception');
            } catch (Throwable $e) {
                expect($e)->toBeInstanceOf(PoolException::class)
                    ->and($e->getMessage())->toBe('Pool is shutting down gracefully')
                ;
            }
        } finally {
            $pool->close();
        }
    });

    it('resolves graceful shutdown immediately if all waiters were cancelled prior to shutdown', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);

        try {
            $conn = await($pool->get());

            $waiter = $pool->get();
            $waiter->cancel();

            $pool->release($conn);

            $shutdown = $pool->closeAsync();
            await($shutdown);

            expect($pool->stats['active_connections'])->toBe(0)
                ->and($pool->stats['pooled_connections'])->toBe(0)
            ;
        } finally {
            $pool->close();
        }
    });

    it('safely handles force close while a waiter is actively connecting', function (): void {
        $pool = new PoolManager(getConfig(), maxSize: 1);
        $waiter = $pool->get();

        $pool->close();

        try {
            await($waiter);
            $this->fail('Expected PoolException to be thrown');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(PoolException::class)
                ->and($e->getMessage())->toBe('Pool closed forcefully')
            ;
        }
    });
});
