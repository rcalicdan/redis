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

    it('batches multiple commands and returns results in order', function () {
        $client = new RedisClient(getConfig());

        try {
            $promise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->set('pipe_key_1', 'val_1');
                $pipe->set('pipe_key_2', 'val_2');
                $pipe->get('pipe_key_1');
                $pipe->mget('pipe_key_1', 'pipe_key_2', 'pipe_missing');
                $pipe->ping('pipeline_pong');
            });

            $results = await($promise);

            expect($results)->toHaveCount(5)
                ->and($results[0])->toBe('OK')
                ->and($results[1])->toBe('OK')
                ->and($results[2])->toBe('val_1')
                ->and($results[3])->toBe(['val_1', 'val_2', null])
                ->and($results[4])->toBe('pipeline_pong')
            ;
        } finally {
            $client->close();
        }
    });

    it('can handle hash commands in a pipeline', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('pipe_hash'));

            $results = await($client->pipeline(function (PipelineInterface $pipe) {
                $hsetCommand = new class (['pipe_hash', 'field1', 'hello', 'field2', 'world']) extends AbstractCommand {
                    public string $id = 'HSET';
                };

                $pipe->executeCommand($hsetCommand);
                $pipe->hgetall('pipe_hash');
                $pipe->del('pipe_hash');
            }));

            expect($results)->toHaveCount(3)
                ->and($results[1])->toBe([
                    'field1' => 'hello',
                    'field2' => 'world',
                ])
                ->and($results[2])->toBe(1)
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
                $pipe->hgetall('wrong_type_key');
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
