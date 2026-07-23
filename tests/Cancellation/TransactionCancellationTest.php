<?php

declare(strict_types=1);

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Exceptions\TransactionException;
use Hibla\Redis\Interfaces\RedisTransactionInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Transaction Cancellation', function (): void {

    it('throws CancelledException when transaction promise is cancelled before acquiring connection', function () {
        $client = new RedisClient(getConfig());
        $key = 'cancel_pre_conn_' . uniqid();

        try {
            $txPromise = $client->transaction(function (RedisTransactionInterface $tx) use ($key) {
                await($tx->set($key, 'should_not_run'));
            });

            $txPromise->cancel();

            expect(fn () => await($txPromise))->toThrow(CancelledException::class);

            await(delay(0.05));
            expect(await($client->get($key)))->toBeNull();
        } finally {
            $client->close();
        }
    });

    it('automatically discards transaction and cleans connection when outer promise is cancelled inside MULTI', function () {
        $client = new RedisClient(getConfig());
        $key = 'tx_cancel_mid_multi_' . uniqid();

        try {
            $txPromise = $client->transaction(function (RedisTransactionInterface $tx) use ($key) {
                await($tx->multi());
                await($tx->set($key, 'uncommitted'));

                await(delay(2.0));
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }
            await(delay(0.02));

            $txPromise->cancel();
            expect(fn () => await($txPromise))->toThrow(CancelledException::class);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 0) {
                    break;
                }
                await(delay(0.01));
            }

            expect(await($client->get($key)))->toBeNull()
                ->and($client->stats['active_connections'])->toBe(0)
                ->and(await($client->ping('CleanPool')))->toBe('CleanPool')
            ;
        } finally {
            $client->close();
        }
    });

    it('taints transaction when an individual command promise inside MULTI is cancelled', function () {
        $client = new RedisClient(getConfig());
        $key = 'should_fail_key_' . uniqid();

        try {
            expect(function () use ($client, $key) {
                await($client->transaction(function (RedisTransactionInterface $tx) use ($key) {
                    await($tx->multi());

                    $cmdPromise = $tx->get('some_key');
                    $cmdPromise->cancel();

                    try {
                        await($cmdPromise);
                    } catch (CancelledException) {
                    }

                    await($tx->set($key, 'value'));
                }));
            })->toThrow(TransactionException::class, 'Transaction aborted due to a previous command error or cancellation');

            expect(await($client->get($key)))->toBeNull();
        } finally {
            $client->close();
        }
    });

    it('automatically unwatches keys when outer transaction promise is cancelled during WATCH', function () {
        $client = new RedisClient(getConfig());
        $key = 'watch_cancel_key_' . uniqid();

        try {
            $txPromise = $client->transaction(function (RedisTransactionInterface $tx) use ($key) {
                await($tx->watch($key));
                await(delay(2.0));
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }
            await(delay(0.02));

            $txPromise->cancel();
            expect(fn () => await($txPromise))->toThrow(CancelledException::class);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 0) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['active_connections'])->toBe(0)
                ->and(await($client->ping('Healthy')))->toBe('Healthy')
            ;
        } finally {
            $client->close();
        }
    });

    it('cancels waiter cleanly when transaction is cancelled while waiting for a connection from pool', function () {
        $client = new RedisClient(getConfig(), maxConnections: 1);

        try {
            $hogPromise = $client->blpop('hog_queue_' . uniqid(), 0);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['active_connections'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            $txPromise = $client->transaction(function (RedisTransactionInterface $tx) {
                await($tx->ping());
            });

            for ($attempt = 0; $attempt < 50; $attempt++) {
                if ($client->stats['waiting_requests'] === 1) {
                    break;
                }
                await(delay(0.01));
            }

            expect($client->stats['waiting_requests'])->toBe(1);

            $txPromise->cancel();

            expect(fn () => await($txPromise))->toThrow(CancelledException::class)
                ->and($client->stats['waiting_requests'])->toBe(0)
            ;

            $hogPromise->cancel();

            try {
                await($hogPromise);
            } catch (Throwable) {
            }
        } finally {
            $client->close();
        }
    });

    it('executes completely on Redis even if the user cancels the exec() promise due to uninterruptible semantics', function () {
        $client = new RedisClient(getConfig());
        $key = 'uninterruptible_key_' . uniqid();

        try {
            $txPromise = $client->transaction(function (RedisTransactionInterface $tx) use ($key) {
                await($tx->multi());
                await($tx->set($key, 'done'));

                $execPromise = $tx->exec();
                $execPromise->cancel();

                try {
                    await($execPromise);
                } catch (CancelledException) {
                }

                await(delay(0.05));
            });

            try {
                await($txPromise);
            } catch (CancelledException) {
            }

            expect(await($client->get($key)))->toBe('done');

        } finally {
            $client->close();
        }
    });
});
