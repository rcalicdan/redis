<?php

declare(strict_types=1);

use Hibla\Promise\Promise;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Command\PubSub\SubscribeCommand;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;

describe('RedisClient - Atomic Operations', function (): void {

    it('executes a basic atomic block and returns parsed results', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('atomic_k1', 'atomic_k2'));

            $results = await($client->atomic(function (PipelineInterface $pipe) {
                $pipe->set('atomic_k1', 'val1');
                $pipe->set('atomic_k2', 'val2');
                $pipe->get('atomic_k1');
                $pipe->mget('atomic_k1', 'atomic_missing');
                $pipe->ping('atomic_pong');
            }));

            expect($results)->toHaveCount(5)
                ->and($results[0])->toBe('OK')
                ->and($results[1])->toBe('OK')
                ->and($results[2])->toBe('val1')
                ->and($results[3])->toBe(['val1', null])
                ->and($results[4])->toBe('atomic_pong')
            ;
        } finally {
            $client->close();
        }
    });

    it('correctly parses complex responses like HGETALL', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('atomic_hash'));

            $results = await($client->atomic(function (PipelineInterface $pipe) {
                $hsetCommand = new class (['atomic_hash', 'f1', 'v1', 'f2', 'v2']) extends AbstractCommand {
                    public string $id = 'HSET';
                };

                $pipe->executeCommand($hsetCommand);
                $pipe->hgetall('atomic_hash');
            }));

            expect($results)->toHaveCount(2)
                ->and($results[0])->toBe(2)
                ->and($results[1])->toBe(['f1' => 'v1', 'f2' => 'v2'])
            ;
        } finally {
            $client->close();
        }
    });

    it('handles an empty atomic block gracefully', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->atomic(function (PipelineInterface $pipe) {
                // Do nothing
            }));

            expect($results)->toBeArray()->toBeEmpty()
                ->and($client->stats['active_connections'])->toBe(0)
            ;
        } finally {
            $client->close();
        }
    });

    it('embeds execution errors inside the result array without failing the whole block', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->set('atomic_string', 'i_am_a_string'));

            $results = await($client->atomic(function (PipelineInterface $pipe) {
                $pipe->ping('before_error');
                $pipe->hgetall('atomic_string');
                $pipe->ping('after_error');
            }));

            expect($results)->toHaveCount(3)
                ->and($results[0])->toBe('before_error')
                ->and($results[1])->toBeInstanceOf(RedisException::class)
                ->and($results[1]->getMessage())->toContain('WRONGTYPE')
                ->and($results[2])->toBe('after_error')
            ;
        } finally {
            $client->close();
        }
    });

    it('maintains pool health and isolates connections correctly', function () {
        $client = new RedisClient(getConfig(), maxConnections: 5);

        try {
            $promise1 = $client->atomic(function (PipelineInterface $pipe) {
                $pipe->set('atomic_c1', '1');
            });
            $promise2 = $client->atomic(function (PipelineInterface $pipe) {
                $pipe->set('atomic_c2', '2');
            });

            await(Promise::all([$promise1, $promise2]));

            expect($client->stats['active_connections'])->toBe(0)
                ->and(await($client->get('atomic_c1')))->toBe('1')
                ->and(await($client->get('atomic_c2')))->toBe('2')
            ;
        } finally {
            $client->close();
        }
    });

    it('rejects the entire promise with the root cause if a syntax error breaks the MULTI block', function () {
        $client = new RedisClient(getConfig());

        try {
            $promise = $client->atomic(function (PipelineInterface $pipe) {
                $pipe->ping('alive');

                $brokenCommand = new class (['only_one_arg']) extends AbstractCommand {
                    public string $id = 'SET';
                };
                $pipe->executeCommand($brokenCommand);

                $pipe->ping('alive_again');
            });

            expect(fn () => await($promise))
                ->toThrow(RedisException::class, "ERR wrong number of arguments for 'set' command")
            ;

            expect(await($client->ping('healthy')))->toBe('healthy');
        } finally {
            $client->close();
        }
    });

    it('throws LogicException if pipeline is modified after execution', function () {
        $client = new RedisClient(getConfig());

        try {
            $leakedPipe = null;

            await($client->atomic(function (PipelineInterface $pipe) use (&$leakedPipe) {
                $pipe->ping('legitimate');
                $leakedPipe = $pipe;
            }));

            expect(fn () => $leakedPipe->set('late_key', 'late_val'))
                ->toThrow(LogicException::class, 'Cannot add commands to a pipeline that has already been executed.')
            ;
        } finally {
            $client->close();
        }
    });

    it('does not block the connection when blocking commands are used inside an atomic block', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('atomic_empty_list'));

            $results = await($client->atomic(function (PipelineInterface $pipe) {
                $pipe->blpop('atomic_empty_list', 10);
            }));

            expect($results)->toHaveCount(1)
                ->and($results[0])->toBeNull()
            ;
        } finally {
            $client->close();
        }
    });

    it('handles massive command volumes accurately in a single TCP write', function () {
        $client = new RedisClient(getConfig());

        try {
            $count = 5000;

            $results = await($client->atomic(function (PipelineInterface $pipe) use ($count) {
                for ($i = 0; $i < $count; $i++) {
                    $pipe->set("mass_atomic_{$i}", "val_{$i}");
                }
            }));

            expect($results)->toHaveCount($count)
                ->and($results[0])->toBe('OK')
                ->and($results[$count - 1])->toBe('OK')
            ;

            $keys = [];
            for ($i = 0; $i < $count; $i++) {
                $keys[] = "mass_atomic_{$i}";
            }
            $deleted = await($client->del(...$keys));

            expect($deleted)->toBe($count);
        } finally {
            $client->close();
        }
    });

    it('rejects atomic operations containing raw pub/sub commands', function () {
        $client = new RedisClient(getConfig());

        try {
            $promise = $client->atomic(function (PipelineInterface $pipe) {
                $pipe->ping('alive');
                $pipe->executeCommand(new SubscribeCommand(['atomic_chan']));
            });

            expect(fn () => await($promise))->toThrow(
                RedisException::class,
                'Pub/Sub commands (SUBSCRIBE) cannot be executed on the general connection pool'
            );
        } finally {
            $client->close();
        }
    });
});
