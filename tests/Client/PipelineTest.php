<?php

declare(strict_types=1);

use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Explicit Pipelining', function (): void {

    it('executes an empty pipeline without error', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                // Do nothing
            }));

            expect($results)->toBeArray()->toBeEmpty()
                ->and($client->stats['total_connections'])->toBe(0)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline String and Numeric commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('p_str', 'p_num', 'p_float', 'p_ex'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->set('p_str', 'val')
                     ->get('p_str')
                     ->mget('p_str', 'missing')
                     ->setex('p_ex', 10, 'exp')
                     ->incr('p_num')
                     ->decr('p_num')
                     ->incrby('p_num', 5)
                     ->incrbyfloat('p_float', 2.5)
                ;
            }));

            expect($results)->toHaveCount(8)
                ->and($results[0])->toBe('OK')
                ->and($results[1])->toBe('val')
                ->and($results[2])->toBe(['val', null])
                ->and($results[3])->toBe('OK')
                ->and($results[4])->toBe(1)
                ->and($results[5])->toBe(0)
                ->and($results[6])->toBe(5)
                ->and((float) $results[7])->toBe(2.5)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline Key management commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->set('p_k1', 'v'));
            await($client->set('p_k2', 'v'));
            await($client->set('p_k3', 'v'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->exists('p_k1', 'p_k2')
                     ->expire('p_k1', 60)
                     ->ttl('p_k1')
                     ->type('p_k1')
                     ->del('p_k2')
                     ->unlink('p_k3')
                ;
            }));

            expect($results)->toHaveCount(6)
                ->and($results[0])->toBe(2)
                ->and($results[1])->toBe(1)
                ->and($results[2])->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
                ->and($results[3])->toBe('string')
                ->and($results[4])->toBe(1)
                ->and($results[5])->toBe(1)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline Hash commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('p_hash'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->hset('p_hash', 'f1', 'v1', 'f2', 'v2')
                     ->hget('p_hash', 'f1')
                     ->hmget('p_hash', 'f1', 'missing')
                     ->hexists('p_hash', 'f2')
                     ->hgetall('p_hash')
                     ->hdel('p_hash', 'f1')
                ;
            }));

            expect($results)->toHaveCount(6)
                ->and($results[0])->toBe(2)
                ->and($results[1])->toBe('v1')
                ->and($results[2])->toBe(['v1', null])
                ->and($results[3])->toBe(1)
                ->and($results[4])->toBe(['f1' => 'v1', 'f2' => 'v2'])
                ->and($results[5])->toBe(1)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline List commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('p_list', 'p_blpop_list'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->lpush('p_list', 'job2', 'job1')
                     ->rpush('p_list', 'job3')
                     ->llen('p_list')
                     ->lpop('p_list')
                     ->rpop('p_list')
                     // Setup for BLPOP
                     ->lpush('p_blpop_list', 'instant_pop')
                     ->blpop('p_blpop_list', 1)
                ;
            }));

            expect($results)->toHaveCount(7)
                ->and($results[0])->toBe(2) // LPUSH
                ->and($results[1])->toBe(3) // RPUSH
                ->and($results[2])->toBe(3) // LLEN
                ->and($results[3])->toBe('job1') // LPOP
                ->and($results[4])->toBe('job3') // RPOP
                ->and($results[5])->toBe(1) // LPUSH for BLPOP
                ->and($results[6])->toBe(['p_blpop_list', 'instant_pop']) // BLPOP
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline Set commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('p_set'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->sadd('p_set', 'm1', 'm2', 'm3')
                     ->sismember('p_set', 'm1')
                     ->smembers('p_set')
                     ->srem('p_set', 'm3')
                ;
            }));

            $members = $results[2];
            sort($members); // Order of sets is not guaranteed by Redis

            expect($results)->toHaveCount(4)
                ->and($results[0])->toBe(3)
                ->and($results[1])->toBe(1)
                ->and($members)->toBe(['m1', 'm2', 'm3'])
                ->and($results[3])->toBe(1)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline Sorted Set (ZSet) commands', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('p_zset'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $pipe->zadd('p_zset', 10, 'p1', 20, 'p2')
                     ->zscore('p_zset', 'p1')
                     ->zrange('p_zset', 0, -1)
                     ->zrem('p_zset', 'p2')
                ;
            }));

            expect($results)->toHaveCount(4)
                ->and($results[0])->toBe(2)
                ->and($results[1])->toBe('10')
                ->and($results[2])->toBe(['p1', 'p2'])
                ->and($results[3])->toBe(1)
            ;
        } finally {
            $client->close();
        }
    });

    it('can pipeline Connection, PubSub, and Custom commands', function () {
        $client = new RedisClient(getConfig());

        try {
            $customCommand = new class (['custom_ping']) extends AbstractCommand {
                public string $id = 'PING';
            };

            $results = await($client->pipeline(function (PipelineInterface $pipe) use ($customCommand) {
                $pipe->ping('alive')
                     ->publish('p_chan', 'msg')
                     ->executeCommand($customCommand)
                ;
            }));

            expect($results)->toHaveCount(3)
                ->and($results[0])->toBe('alive')
                ->and($results[1])->toBe(0) // No subscribers in this isolated test
                ->and($results[2])->toBe('custom_ping')
            ;
        } finally {
            $client->close();
        }
    });

    it('rejects the entire pipeline promise if a command fails (Promise::all behavior)', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->set('wrong_type_key', 'string_value'));

            $promise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->ping('first');
                $pipe->hgetall('wrong_type_key'); // This will throw WRONGTYPE
                $pipe->ping('third');
            });

            expect(fn () => await($promise))
                ->toThrow(RedisException::class, 'WRONGTYPE Operation against a key holding the wrong kind of value')
            ;

        } finally {
            $client->close();
        }
    });

    it('locks the pipeline and throws LogicException if modified after execution', function () {
        $client = new RedisClient(getConfig());

        try {
            /** @var PipelineInterface|null $leakedPipe */
            $leakedPipe = null;

            $results = await($client->pipeline(function (PipelineInterface $pipe) use (&$leakedPipe) {
                $pipe->ping('legitimate');
                $leakedPipe = $pipe;
            }));

            expect($results)->toBe(['legitimate']);

            expect(fn () => $leakedPipe->set('late_key', 'late_val'))
                ->toThrow(LogicException::class, 'Cannot add commands to a pipeline that has already been executed.')
            ;

        } finally {
            $client->close();
        }
    });

    it('can mass insert and delete reliably via pipeline', function () {
        $client = new RedisClient(getConfig());

        try {
            $insertResults = await($client->pipeline(function (PipelineInterface $pipe) {
                for ($i = 0; $i < 100; $i++) {
                    $pipe->set("mass_key_{$i}", "val_{$i}");
                }
            }));

            expect($insertResults)->toHaveCount(100)
                ->and($insertResults[0])->toBe('OK')
                ->and($insertResults[99])->toBe('OK')
            ;

            $keysToDelete = [];
            for ($i = 0; $i < 100; $i++) {
                $keysToDelete[] = "mass_key_{$i}";
            }

            $deletedCount = await($client->del(...$keysToDelete));
            expect($deletedCount)->toBe(100);

        } finally {
            $client->close();
        }
    });

    it('uses exactly one connection from the pool regardless of command count', function () {
        $client = new RedisClient(getConfig(), maxConnections: 5);

        try {
            $promise = $client->pipeline(function (PipelineInterface $pipe) {
                for ($i = 0; $i < 5; $i++) {
                    $pipe->ping("Ping {$i}");
                }

                $pipe->blpop('empty_test_list_for_pipeline', 1);
            });

            $statsMidFlight = [];

            for ($attempt = 0; $attempt < 50; $attempt++) {
                $statsMidFlight = $client->stats;
                if ($statsMidFlight['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($statsMidFlight)->not->toBeEmpty();

            $results = await($promise);
            $statsAfter = $client->stats;

            expect($results)->toHaveCount(6)
                ->and($statsMidFlight['active_connections'])->toBe(1)
                ->and($statsMidFlight['total_connections'])->toBe(1)
                ->and($statsAfter['active_connections'])->toBe(0)
                ->and($statsAfter['pooled_connections'])->toBe(1)
            ;

        } finally {
            $client->close();
        }
    });
});
