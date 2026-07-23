<?php

declare(strict_types=1);

use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Exceptions\TransactionException;
use Hibla\Redis\Interfaces\RedisTransactionInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;

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
                        // Suppress exception to continue block
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

    it('prevents executing commands on a transaction handle after exec or discard', function () {
        $client = new RedisClient(getConfig());

        try {
            /** @var RedisTransactionInterface|null $leakedTx */
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

    it('prevents invalid transaction states (nested MULTI, WATCH inside MULTI, EXEC without MULTI)', function () {
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

        } finally {
            $client->close();
        }
    });
});
