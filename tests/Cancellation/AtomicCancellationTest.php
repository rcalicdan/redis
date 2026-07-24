<?php

declare(strict_types=1);

use Hibla\EventLoop\Loop;
use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Atomic Cancellation', function (): void {

    it('throws CancelledException and cleans waiter when atomic promise is cancelled before acquiring connection', function () {
        $client = new RedisClient(getConfig(), maxConnections: 1);
        $key = 'atomic_cancel_pre_conn_' . uniqid();

        try {
            $hogPromise = $client->blpop('atomic_hog_queue', 0);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            $atomicPromise = $client->atomic(function (PipelineInterface $pipe) use ($key) {
                $pipe->set($key, 'should_not_run');
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['waiting_requests'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['waiting_requests'])->toBe(1);

            $atomicPromise->cancel();

            expect(fn () => await($atomicPromise))->toThrow(CancelledException::class)
                ->and($client->stats['waiting_requests'])->toBe(0)
            ;

            $hogPromise->cancel();

            try {
                await($hogPromise);
            } catch (Throwable) {
                // Expected
            }

            await(delay(0.05));
            expect(await($client->get($key)))->toBeNull();

        } finally {
            $client->close();
        }
    });

    it('keeps connection alive and pooled after cancelling an atomic block of non-blocking commands mid-flight', function () {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            await($client->ping());

            $atomicPromise = $client->atomic(function (PipelineInterface $pipe) {
                for ($i = 0; $i < 5000; $i++) {
                    $pipe->ping("Ping {$i}");
                }
            });

            Loop::nextTick(function () use ($atomicPromise) {
                $atomicPromise->cancel();
            });

            expect(fn () => await($atomicPromise))->toThrow(CancelledException::class);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['pooled_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['active_connections'])->toBe(0)
                ->and($client->stats['pooled_connections'])->toBe(1)
            ;

            expect(await($client->ping('Fully healthy')))->toBe('Fully healthy');

        } finally {
            $client->close();
        }
    });

    it('forcefully closes connection if an atomic block with a blocking command is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig(), minConnections: 1, maxConnections: 1);

        try {
            await($client->ping());
            $atomicPromise = $client->atomic(function (PipelineInterface $pipe) {
                for ($i = 0; $i < 2000; $i++) {
                    $pipe->ping('ok');
                }
                $pipe->blpop('atomic_cancel_list', 10);
            });

            Loop::nextTick(function () use ($atomicPromise) {
                $atomicPromise->cancel();
            });

            expect(fn () => await($atomicPromise))->toThrow(CancelledException::class);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['pooled_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['active_connections'])->toBe(0)
                ->and($client->stats['pooled_connections'])->toBe(1)
            ;

            expect(await($client->ping('I am alive')))->toBe('I am alive');

        } finally {
            $client->close();
        }
    });

    it('executes completely on Redis even if the user cancels the promise after writing to the socket', function () {
        $client = new RedisClient(getConfig());
        $key = 'atomic_uninterruptible_key_' . uniqid();

        try {
            await($client->ping());

            $atomicPromise = $client->atomic(function (PipelineInterface $pipe) use ($key) {
                $pipe->set($key, 'done');
            });

            Loop::nextTick(function () use ($atomicPromise) {
                $atomicPromise->cancel();
            });

            try {
                await($atomicPromise);
            } catch (CancelledException) {
                // Expected
            }

            await(delay(0.05));

            expect(await($client->get($key)))->toBe('done');

        } finally {
            $client->close();
        }
    });
});
