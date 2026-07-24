<?php

declare(strict_types=1);

use Hibla\Promise\Promise;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Command\PubSub\SubscribeCommand;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Exceptions\TransactionException;
use Hibla\Redis\Interfaces\RedisTransactionInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Transactions', function (): void {

    it('automatically executes MULTI block if exec() is omitted', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('tx_auto_1', 'val1'));
                await($tx->set('tx_auto_2', 'val2'));
            }));

            expect($results)->toBe(['OK', 'OK'])
                ->and(await($client->get('tx_auto_1')))->toBe('val1')
                ->and(await($client->get('tx_auto_2')))->toBe('val2')
            ;

        } finally {
            $client->close();
        }
    });

    it('supports explicit exec() inside transaction block', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('tx_explicit_1', 'hello'));
                await($tx->get('tx_explicit_1'));

                return await($tx->exec());
            }));

            expect($results)->toBe(['OK', 'hello']);

        } finally {
            $client->close();
        }
    });

    it('allows explicit discard() to abort transaction', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('tx_discard_key', 'should_not_exist'));

                await($tx->discard());
            }));

            expect(await($client->get('tx_discard_key')))->toBeNull();

        } finally {
            $client->close();
        }
    });

    it('executes all standard commands (get, set, del, mget, hgetall, ping, executeCommand) inside MULTI', function () {
        $client = new RedisClient(getConfig());

        try {
            $hset = new class (['tx_hash', 'field1', 'value1']) extends AbstractCommand {
                public string $id = 'HSET';
            };
            await($client->executeCommand($hset));
            await($client->set('tx_str_1', 'hello'));
            await($client->set('tx_str_2', 'world'));

            $results = await($client->transaction(function (RedisTransactionInterface $tx) use ($hset) {
                await($tx->multi());

                $r1 = await($tx->ping('TX_PONG'));
                $r2 = await($tx->set('tx_str_3', 'foo'));
                $r3 = await($tx->get('tx_str_1'));
                $r4 = await($tx->mget('tx_str_1', 'tx_str_2', 'missing'));
                $r5 = await($tx->hgetall('tx_hash'));
                $r6 = await($tx->del('tx_str_3'));
                $r7 = await($tx->executeCommand($hset));

                expect($r1)->toBe('QUEUED')
                    ->and($r2)->toBe('QUEUED')
                    ->and($r3)->toBe('QUEUED')
                    ->and($r4)->toBe('QUEUED')
                    ->and($r5)->toBe('QUEUED')
                    ->and($r6)->toBe('QUEUED')
                    ->and($r7)->toBe('QUEUED')
                ;

                return await($tx->exec());
            }));

            expect($results)->toBe([
                'TX_PONG',
                'OK',
                'hello',
                ['hello', 'world', null],
                ['field1' => 'value1'],
                1,
                0,
            ]);

        } finally {
            $client->close();
        }
    });

    it('executes transaction conditionally using WATCH when key is untouched', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->set('watch_clean', '100'));

            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->watch('watch_clean'));

                $val = (int) await($tx->get('watch_clean'));

                await($tx->multi());
                await($tx->set('watch_clean', (string) ($val + 50)));

                return await($tx->exec());
            }));

            expect($results)->toBe(['OK'])
                ->and(await($client->get('watch_clean')))->toBe('150')
            ;

        } finally {
            $client->close();
        }
    });

    it('returns null from exec() if a watched key is modified by another client', function () {
        $client = new RedisClient(getConfig());
        $otherClient = new RedisClient(getConfig());

        try {
            await($client->set('watch_conflict', '100'));

            $results = await($client->transaction(function (RedisTransactionInterface $tx) use ($otherClient) {
                await($tx->watch('watch_conflict'));

                await($otherClient->set('watch_conflict', '999'));

                await($tx->multi());
                await($tx->set('watch_conflict', '200'));

                return await($tx->exec());
            }));

            expect($results)->toBeNull()
                ->and(await($client->get('watch_conflict')))->toBe('999')
            ;

        } finally {
            $client->close();
            $otherClient->close();
        }
    });

    it('fails EXEC if any one of multiple watched keys is modified', function () {
        $client = new RedisClient(getConfig());
        $otherClient = new RedisClient(getConfig());

        try {
            await($client->set('w_key_1', '1'));
            await($client->set('w_key_2', '2'));

            $result = await($client->transaction(function (RedisTransactionInterface $tx) use ($otherClient) {
                await($tx->watch('w_key_1', 'w_key_2'));

                await($otherClient->set('w_key_2', 'modified'));

                await($tx->multi());
                await($tx->set('w_key_1', 'new_1'));

                return await($tx->exec());
            }));

            expect($result)->toBeNull()
                ->and(await($client->get('w_key_1')))->toBe('1')
            ;

        } finally {
            $client->close();
            $otherClient->close();
        }
    });

    it('fails EXEC if a watched non-existent key is created before exec', function () {
        $client = new RedisClient(getConfig());
        $otherClient = new RedisClient(getConfig());

        try {
            await($client->del('w_missing_key'));

            $result = await($client->transaction(function (RedisTransactionInterface $tx) use ($otherClient) {
                await($tx->watch('w_missing_key'));

                await($otherClient->set('w_missing_key', 'created'));

                await($tx->multi());
                await($tx->set('w_missing_key', 'tx_override'));

                return await($tx->exec());
            }));

            expect($result)->toBeNull()
                ->and(await($client->get('w_missing_key')))->toBe('created')
            ;

        } finally {
            $client->close();
            $otherClient->close();
        }
    });

    it('cancels watching when unwatch() is called explicitly', function () {
        $client = new RedisClient(getConfig());
        $otherClient = new RedisClient(getConfig());

        try {
            await($client->set('unwatch_key', 'initial'));

            $results = await($client->transaction(function (RedisTransactionInterface $tx) use ($otherClient) {
                await($tx->watch('unwatch_key'));
                await($tx->unwatch());

                await($otherClient->set('unwatch_key', 'modified_by_other'));

                await($tx->multi());
                await($tx->set('unwatch_key', 'set_by_tx'));

                return await($tx->exec());
            }));

            expect($results)->toBe(['OK'])
                ->and(await($client->get('unwatch_key')))->toBe('set_by_tx')
            ;

        } finally {
            $client->close();
            $otherClient->close();
        }
    });

    it('allows normal commands on the dedicated connection before calling multi()', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->set('pre_tx_key', 'pre_val'));

            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                $readVal = await($tx->get('pre_tx_key'));
                expect($readVal)->toBe('pre_val');

                await($tx->multi());
                await($tx->set('post_tx_key', 'post_' . $readVal));

                return await($tx->exec());
            }));

            expect($results)->toBe(['OK'])
                ->and(await($client->get('post_tx_key')))->toBe('post_pre_val')
            ;

        } finally {
            $client->close();
        }
    });

    it('allows calling discard() multiple times without throwing errors', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('idempotent_key', 'val'));

                $d1 = await($tx->discard());
                $d2 = await($tx->discard());

                expect($d1)->toBe('OK')
                    ->and($d2)->toBe('OK')
                ;
            }));

        } finally {
            $client->close();
        }
    });

    it('executes an empty MULTI block cleanly', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());

                return await($tx->exec());
            }));

            expect($results)->toBeArray()->toBeEmpty();

        } finally {
            $client->close();
        }
    });

    it('queues BLPOP without blocking inside a transaction', function () {
        $client = new RedisClient(getConfig());

        try {
            $results = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->blpop('empty_tx_list', 0));

                return await($tx->exec());
            }));

            expect($results)->toBe([null]);

        } finally {
            $client->close();
        }
    });

    it('runs concurrent transactions on separate connections safely', function () {
        $client = new RedisClient(getConfig(), maxConnections: 3);

        try {
            $tx1 = $client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('conc_tx_1', 'val1'));
                await(delay(0.02));

                return await($tx->exec());
            });

            $tx2 = $client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->multi());
                await($tx->set('conc_tx_2', 'val2'));

                return await($tx->exec());
            });

            $results = await(Promise::all([$tx1, $tx2]));

            expect($results[0])->toBe(['OK'])
                ->and($results[1])->toBe(['OK'])
                ->and(await($client->get('conc_tx_1')))->toBe('val1')
                ->and(await($client->get('conc_tx_2')))->toBe('val2')
            ;

        } finally {
            $client->close();
        }
    });

    it('supports custom commands inside MULTI via executeCommand()', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->del('custom_counter'));

            $incrCmd = new class (['custom_counter']) extends AbstractCommand {
                public string $id = 'INCR';
            };

            $results = await($client->transaction(function (RedisTransactionInterface $tx) use ($incrCmd) {
                await($tx->multi());
                await($tx->executeCommand($incrCmd));
                await($tx->executeCommand($incrCmd));

                return await($tx->exec());
            }));

            expect($results)->toBe([1, 2]);

        } finally {
            $client->close();
        }
    });

    it('taints transaction and rejects subsequent commands if a command fails during queuing', function () {
        $client = new RedisClient(getConfig());

        try {
            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->multi());

                    $brokenCmd = new class ([]) extends AbstractCommand {
                        public string $id = 'WRONG_SYNTAX_CMD';
                    };

                    try {
                        await($tx->executeCommand($brokenCmd));
                    } catch (Throwable) {
                    }

                    await($tx->set('should_fail', 'val'));
                }));
            })->toThrow(TransactionException::class, 'Transaction aborted due to a previous command error');

            expect(await($client->get('should_fail')))->toBeNull();

        } finally {
            $client->close();
        }
    });

    it('automatically discards and releases connection when closure throws an exception', function () {
        $client = new RedisClient(getConfig());

        try {
            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->multi());
                    await($tx->set('tx_error_key', 'bad'));

                    throw new RuntimeException('User code crashed!');
                }));
            })->toThrow(RuntimeException::class, 'User code crashed!');

            expect(await($client->get('tx_error_key')))->toBeNull();

            expect(await($client->ping('Alive')))->toBe('Alive');

        } finally {
            $client->close();
        }
    });

    it('returns user-defined custom return value when MULTI is omitted', function () {
        $client = new RedisClient(getConfig());

        try {
            $customResult = await($client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->set('non_multi_key', 'hello'));

                return 'custom_response_value';
            }));

            expect($customResult)->toBe('custom_response_value')
                ->and(await($client->get('non_multi_key')))->toBe('hello')
            ;

        } finally {
            $client->close();
        }
    });

    it('maintains pool health and returns connections cleanly after repeated failures', function () {
        $client = new RedisClient(getConfig(), minConnections: 2, maxConnections: 2);

        try {
            for ($i = 0; $i < 5; $i++) {
                try {
                    await($client->transaction(function (RedisTransactionInterface $tx) use ($i) {
                        await($tx->multi());
                        await($tx->set("fail_key_{$i}", 'val'));

                        throw new RuntimeException("Simulated crash {$i}");
                    }));
                } catch (RuntimeException) {
                }
            }

            expect($client->stats['active_connections'])->toBe(0)
                ->and(await($client->ping('PoolIsClean')))->toBe('PoolIsClean')
            ;

        } finally {
            $client->close();
        }
    });

    it('prevents executing commands on a transaction handle after exec or discard', function () {
        $client = new RedisClient(getConfig());

        try {
            $leakedTx = null;

            await($client->transaction(function (RedisTransactionInterface $tx) use (&$leakedTx) {
                await($tx->multi());
                await($tx->set('k1', 'v1'));
                await($tx->exec());
                $leakedTx = $tx;
            }));

            expect(function () use ($leakedTx) {
                await($leakedTx->set('k2', 'v2'));
            })->toThrow(TransactionException::class, 'transaction is no longer active');

        } finally {
            $client->close();
        }
    });

    it('prevents invalid transaction states (nested MULTI, WATCH inside MULTI, EXEC without MULTI, DISCARD without MULTI)', function () {
        $client = new RedisClient(getConfig());

        try {
            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->multi());
                    await($tx->multi());
                }));
            })->toThrow(TransactionException::class, 'MULTI calls cannot be nested');

            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->multi());
                    await($tx->watch('some_key'));
                }));
            })->toThrow(TransactionException::class, 'WATCH inside MULTI is not allowed');

            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->exec());
                }));
            })->toThrow(TransactionException::class, 'EXEC without MULTI');

            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->discard());
                }));
            })->toThrow(TransactionException::class, 'DISCARD without MULTI');

        } finally {
            $client->close();
        }
    });

    it('rejects raw pub/sub commands executed inside a transaction', function () {
        $client = new RedisClient(getConfig());

        try {
            expect(function () use ($client) {
                await($client->transaction(function (RedisTransactionInterface $tx) {
                    await($tx->executeCommand(new SubscribeCommand(['tx_chan'])));
                }));
            })->toThrow(
                RedisException::class,
                'Pub/Sub commands (SUBSCRIBE) cannot be executed on the general connection pool'
            );
        } finally {
            $client->close();
        }
    });
});
