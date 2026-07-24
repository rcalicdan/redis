<?php

declare(strict_types=1);

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Command\PubSub\SubscribeCommand;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('Pipeline Cancellation & Resource Safety', function (): void {

    it('cancels a pipeline waiting in the pool queue cleanly', function () {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            $hogPromise = $client->blpop('hog_list', 5);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['active_connections'])->toBe(1)
                ->and($client->stats['waiting_requests'])->toBe(0)
            ;

            $pipelinePromise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->ping('should_never_run');
            });

            expect($client->stats['waiting_requests'])->toBe(1);

            $pipelinePromise->cancel();

            expect(fn () => await($pipelinePromise))
                ->toThrow(CancelledException::class)
                ->and($client->stats['waiting_requests'])->toBe(0)
            ;

            $hogPromise->cancel();

            try {
                await($hogPromise);
            } catch (Throwable) {
                // expected
            }
        } finally {
            $client->close();
        }
    });

    it('forcefully closes connection if a pipeline with a blocking command is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig(), minConnections: 1, maxConnections: 1);

        try {
            $pipelinePromise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->ping('ok');
                $pipe->blpop('empty_cancel_list', 10);
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            await(delay(0.02));

            expect($client->stats['active_connections'])->toBe(1);

            $pipelinePromise->cancel();

            expect(fn () => await($pipelinePromise))->toThrow(CancelledException::class);

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

    it('keeps connection alive if a pipeline with only non-blocking commands is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            $pipelinePromise = $client->pipeline(function (PipelineInterface $pipe) {
                for ($i = 0; $i < 5000; $i++) {
                    $pipe->ping("Ping {$i}");
                }
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            await(delay(0.005));

            $pipelinePromise->cancel();

            expect(fn () => await($pipelinePromise))->toThrow(CancelledException::class);

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

    it('prevents pool corruption by rejecting pipelines containing raw pub/sub commands', function () {
        $client = new RedisClient(getConfig());

        try {
            $pipelinePromise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->ping('ok');
                $pipe->executeCommand(new SubscribeCommand(['pipe_poison_channel']));
            });

            expect(fn () => await($pipelinePromise))->toThrow(
                RedisException::class,
                'Pub/Sub commands (SUBSCRIBE) cannot be executed on the general connection pool'
            );
        } finally {
            $client->close();
        }
    });
});
