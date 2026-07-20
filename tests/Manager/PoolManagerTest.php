<?php

declare(strict_types=1);

use Hibla\Promise\Exceptions\TimeoutException;
use Hibla\Redis\Exceptions\PoolException;
use Hibla\Redis\Manager\PoolManager;

use function Hibla\await;
use function Hibla\delay;

it('initializes asynchronously with the minimum number of connections', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 2, maxSize: 5);

    try {
        for ($i = 0; $i < 40; $i++) {
            if ($pool->stats['pooled_connections'] === 2) {
                break;
            }
            await(delay(0.05));
        }

        $stats = $pool->stats;
        expect($stats['total_connections'])->toBe(2)
            ->and($stats['pooled_connections'])->toBe(2)
            ->and($stats['active_connections'])->toBe(0)
        ;
    } finally {
        $pool->close();
    }
});

it('respects maximum connections and queues waiters', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 1, maxSize: 2);

    try {
        $conn1 = await($pool->get());
        $conn2 = await($pool->get());

        $promise3 = $pool->get();

        expect($promise3->isPending())->toBeTrue()
            ->and($pool->stats['total_connections'])->toBe(2)
            ->and($pool->stats['waiting_requests'])->toBe(1)
        ;

        $pool->release($conn1);

        $conn3 = await($promise3);
        expect($conn3)->toBe($conn1)
            ->and($pool->stats['waiting_requests'])->toBe(0)
        ;
    } finally {
        $pool->close();
    }
});

it('rejects with PoolException when maxWaiters is exceeded', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 1, maxSize: 1, maxWaiters: 1);

    try {
        $conn1 = await($pool->get());

        $promise2 = $pool->get();

        try {
            await($pool->get());
            $this->fail('Expected PoolException to be thrown');
        } catch (PoolException $e) {
            expect($e->getMessage())->toContain('Connection pool exhausted. Max waiters limit (1) reached.');
        }
    } finally {
        $pool->close();
    }
});

it('times out if a connection cannot be acquired within acquireTimeout', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 1, maxSize: 1, acquireTimeout: 0.1);

    try {
        $conn1 = await($pool->get());

        try {
            await($pool->get());
            $this->fail('Expected TimeoutException to be thrown');
        } catch (TimeoutException $e) {
            expect($e->getMessage())->toContain('Acquire timeout of 0.1s exceeded');
        }
    } finally {
        $pool->close();
    }
});

it('discards closed connections and spins up replacements', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 1, maxSize: 1);

    try {
        $conn1 = await($pool->get());

        $conn1->close();

        $pool->release($conn1);

        $conn2 = await($pool->get());

        expect($conn2)->not->toBe($conn1)
            ->and($conn2->isClosed())->toBeFalse()
        ;
    } finally {
        $pool->close();
    }
});

it('shuts down gracefully, waiting for active connections to be released', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 1, maxSize: 2);

    try {
        $conn1 = await($pool->get());

        $closePromise = $pool->closeAsync();

        expect($closePromise->isPending())->toBeTrue()
            ->and($pool->stats['is_graceful_shutdown'])->toBeTrue()
        ;

        $pool->release($conn1);

        await($closePromise);

        expect($pool->stats['total_connections'])->toBe(0)
            ->and($pool->stats['active_connections'])->toBe(0)
        ;
    } finally {
        $pool->close();
    }
});

it('performs health checks on pooled idle connections', function () {
    $config = getConfig();
    $pool = new PoolManager($config, minSize: 2, maxSize: 2);

    try {
        for ($i = 0; $i < 40; $i++) {
            if ($pool->stats['pooled_connections'] === 2) {
                break;
            }
            await(delay(0.05));
        }

        $stats = await($pool->healthCheck());

        expect($stats['total_checked'])->toBe(2)
            ->and($stats['healthy'])->toBe(2)
            ->and($stats['unhealthy'])->toBe(0)
        ;
    } finally {
        $pool->close();
    }
});
