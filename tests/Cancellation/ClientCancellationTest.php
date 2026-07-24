<?php

declare(strict_types=1);

use Hibla\EventLoop\Loop;
use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Command\PubSub\SubscribeCommand;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Query & Command Cancellation', function (): void {
    it('cancels a non-blocking command mid-flight and throws CancelledException', function (): void {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            $blpopPromise = $client->blpop('cancel_list', 10);

            Loop::addTimer(0.05, function () use ($blpopPromise): void {
                $blpopPromise->cancel();
            });

            expect(fn () => await($blpopPromise))
                ->toThrow(CancelledException::class)
            ;

            $pingResult = await($client->ping('StillAlive'));
            expect($pingResult)->toBe('StillAlive');
        } finally {
            $client->close();
        }
    });

    it('cancels a queued waiter when pool is fully saturated and throws CancelledException', function (): void {
        $client = new RedisClient(getConfig(), maxConnections: 1, maxWaiters: 5);

        try {
            $hogPromise = $client->blpop('hog_list', 5);
            await(delay(0.02));

            $waiterPromise = $client->get('some_key');

            expect($client->stats['waiting_requests'])->toBe(1);
            expect($waiterPromise->isPending())->toBeTrue();

            $waiterPromise->cancel();

            expect(fn () => await($waiterPromise))
                ->toThrow(CancelledException::class)
                ->and($client->stats['waiting_requests'])->toBe(0)
            ;

            $hogPromise->cancel();

            try {
                await($hogPromise);
            } catch (Throwable) {
                // expected
            }

            $pong = await($client->ping('PoolHealthy'));
            expect($pong)->toBe('PoolHealthy');
        } finally {
            $client->close();
        }
    });

    it('propagates cancellation down to the connection enqueue promise', function (): void {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            $promise = $client->get('test_cancel_propagate');

            $promise->cancel();

            expect($promise->isCancelled())->toBeTrue();

            await(delay(0.02));
            expect($client->stats['active_connections'])->toBe(0);
        } finally {
            $client->close();
        }
    });

    it('remains fully operational after multiple concurrent command cancellations', function (): void {
        $client = new RedisClient(getConfig(), maxConnections: 3);

        try {
            $promises = [
                $client->blpop('list_a', 5),
                $client->blpop('list_b', 5),
                $client->blpop('list_c', 5),
            ];

            await(delay(0.02));

            foreach ($promises as $p) {
                $p->cancel();
            }

            foreach ($promises as $p) {
                expect(fn () => await($p))->toThrow(CancelledException::class);
            }

            await(delay(0.1));

            $setResult = await($client->set('recovery_key', 'recovery_val'));
            expect($setResult)->toBe('OK');

            $getResult = await($client->get('recovery_key'));
            expect($getResult)->toBe('recovery_val');
        } finally {
            $client->close();
        }
    });

    it('prevents pool corruption by rejecting raw pub/sub commands on the general client', function (): void {
        $client = new RedisClient(getConfig());

        try {
            $promise = $client->executeCommand(new SubscribeCommand(['poison_channel']));

            expect(fn () => await($promise))->toThrow(
                RedisException::class,
                'Pub/Sub commands (SUBSCRIBE) cannot be executed on the general connection pool'
            );

        } finally {
            $client->close();
        }
    });
});
