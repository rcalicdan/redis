<?php

declare(strict_types=1);

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('Pub/Sub Cancellation', function (): void {

    it('throws CancelledException when publish() command is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig());

        try {
            $publishPromise = $client->publish('channel_cancel', 'Should be cancelled');
            $publishPromise->cancel();

            expect(fn () => await($publishPromise))->toThrow(CancelledException::class);
        } finally {
            $client->close();
        }
    });

    it('removes callback and throws CancelledException when subscribe() is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            $promise = $subscriber->subscribe('cancel_sub_channel', function ($ch, $payload) use (&$received) {
                $received[] = $payload;
            });

            // Cancel immediately before the network ACK arrives
            $promise->cancel();

            expect(fn () => await($promise))->toThrow(CancelledException::class);

            // Give the event loop a tick to process the cancellation cleanup
            await(delay(0.05));

            // Publish to the channel and verify the cancelled callback never receives it
            await($client->publish('cancel_sub_channel', 'Test Message'));
            await(delay(0.05));

            expect($received)->toBeEmpty();

        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('removes callback and throws CancelledException when psubscribe() is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            $promise = $subscriber->psubscribe('cancel_psub_*', function ($pat, $ch, $payload) use (&$received) {
                $received[] = $payload;
            });

            $promise->cancel();

            expect(fn () => await($promise))->toThrow(CancelledException::class);

            await(delay(0.05));

            await($client->publish('cancel_psub_test', 'Test Message'));
            await(delay(0.05));

            expect($received)->toBeEmpty();

        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('throws CancelledException when createSubscriber() is cancelled during initialization', function () {
        $client = new RedisClient(getConfig());

        try {
            $subscriberPromise = $client->createSubscriber();

            // Cancel before the TCP connection handshake finishes
            $subscriberPromise->cancel();

            expect(fn () => await($subscriberPromise))->toThrow(CancelledException::class);

        } finally {
            $client->close();
        }
    });

    it('throws CancelledException when unsubscribe() is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            await($subscriber->subscribe('unsub_cancel_channel', fn () => null));

            $unsubPromise = $subscriber->unsubscribe('unsub_cancel_channel');
            $unsubPromise->cancel();

            expect(fn () => await($unsubPromise))->toThrow(CancelledException::class);

        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('throws CancelledException when punsubscribe() is cancelled mid-flight', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            await($subscriber->psubscribe('punsub_cancel_*', fn () => null));

            $punsubPromise = $subscriber->punsubscribe('punsub_cancel_*');
            $punsubPromise->cancel();

            expect(fn () => await($punsubPromise))->toThrow(CancelledException::class);

        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('keeps other channel subscriptions active when one subscription is cancelled', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received1 = [];
            $received2 = [];

            $p1 = $subscriber->subscribe('channel_1', function ($ch, $msg) use (&$received1) {
                $received1[] = $msg;
            });
            $p2 = $subscriber->subscribe('channel_2', function ($ch, $msg) use (&$received2) {
                $received2[] = $msg;
            });

            $p1->cancel();

            expect(fn () => await($p1))->toThrow(CancelledException::class);

            await($p2);

            await($client->publish('channel_1', 'Ignored Message'));
            await($client->publish('channel_2', 'Delivered Message'));
            await(delay(0.05));

            expect($received1)->toBeEmpty()
                ->and($received2)->toBe(['Delivered Message'])
            ;

        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('throws CancelledException when a pipeline containing publish() is cancelled', function () {
        $client = new RedisClient(getConfig());

        try {
            $pipePromise = $client->pipeline(function (PipelineInterface $pipe) {
                $pipe->set('pipe_key', 'val');
                $pipe->publish('pipe_channel', 'pipe_message');
            });

            $pipePromise->cancel();

            expect(fn () => await($pipePromise))->toThrow(CancelledException::class);

        } finally {
            $client->close();
        }
    });

    it('cancels pending reconnect backoff cleanly when subscriber is closed during disconnect', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber(minReconnectInterval: 0.1, maxReconnectInterval: 0.5));

        try {
            await($subscriber->subscribe('reconnect_cancel_ch', fn () => null));

            $killCommand = new class (['KILL', 'TYPE', 'pubsub']) extends AbstractCommand {
                public string $id = 'CLIENT';
            };

            await($client->executeCommand($killCommand));

            await($subscriber->close());
            await(delay(0.2));
            expect(await($client->ping()))->toBe('PONG');
        } finally {
            $client->close();
        }
    });
});
