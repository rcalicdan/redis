<?php

declare(strict_types=1);

use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\RedisClient;

use function Hibla\await;
use function Hibla\delay;

describe('RedisClient - Pub/Sub', function (): void {

    it('can subscribe to a channel and receive messages', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            await($subscriber->subscribe('news', function (string $channel, string $payload) use (&$received) {
                $received[] = ['channel' => $channel, 'payload' => $payload];
            }));

            $subscribersCount = await($client->publish('news', 'Hello World!'));

            await(delay(0.05));

            expect($subscribersCount)->toBeGreaterThanOrEqual(1)
                ->and($received)->toHaveCount(1)
                ->and($received[0])->toBe(['channel' => 'news', 'payload' => 'Hello World!'])
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('can subscribe to a pattern and receive routed messages', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            await($subscriber->psubscribe('notifications.*', function (string $pattern, string $channel, string $payload) use (&$received) {
                $received[] = [
                    'pattern' => $pattern,
                    'channel' => $channel,
                    'payload' => $payload,
                ];
            }));

            await($client->publish('notifications.email', 'Email sent'));
            await($client->publish('notifications.sms', 'SMS sent'));
            await($client->publish('other_channel', 'Ignored'));

            await(delay(0.05));

            expect($received)->toHaveCount(2)
                ->and($received[0]['channel'])->toBe('notifications.email')
                ->and($received[1]['channel'])->toBe('notifications.sms')
                ->and($received[0]['pattern'])->toBe('notifications.*')
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('supports multiple callbacks for the same channel', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $callCount1 = 0;
            $callCount2 = 0;

            $cb1 = function () use (&$callCount1) {
                $callCount1++;
            };
            $cb2 = function () use (&$callCount2) {
                $callCount2++;
            };

            await($subscriber->subscribe('multi_test', $cb1));
            await($subscriber->subscribe('multi_test', $cb2));

            await($client->publish('multi_test', 'Ping'));
            await(delay(0.05));

            expect($callCount1)->toBe(1)
                ->and($callCount2)->toBe(1)
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('stops receiving messages after unsubscribing', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            await($subscriber->subscribe('unsub_test', function ($channel, $payload) use (&$received) {
                $received[] = $payload;
            }));

            await($client->publish('unsub_test', 'Message 1'));
            await(delay(0.05));

            await($subscriber->unsubscribe('unsub_test'));

            await($client->publish('unsub_test', 'Message 2'));
            await(delay(0.05));

            expect($received)->toBe(['Message 1']);
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('automatically reconnects and restores subscriptions if the connection drops', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber(minReconnectInterval: 0.05, maxReconnectInterval: 0.2));

        try {
            $received = [];

            await($subscriber->subscribe('resilience', function ($channel, $payload) use (&$received) {
                $received[] = $payload;
            }));

            await($client->publish('resilience', 'Before drop'));
            await(delay(0.05));

            $killCommand = new class (['KILL', 'TYPE', 'pubsub']) extends AbstractCommand {
                public string $id = 'CLIENT';
            };

            await($client->executeCommand($killCommand));

            $attempts = 0;

            while (! in_array('After reconnect', $received, true) && $attempts < 20) {
                await(delay(0.1));
                await($client->publish('resilience', 'After reconnect'));
                $attempts++;
            }

            expect($received)->toContain('Before drop')
                ->and($received)->toContain('After reconnect')
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('only removes the specific callback when unsubscribing', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $calls = ['cb1' => 0, 'cb2' => 0, 'cb3' => 0];

            $cb1 = function () use (&$calls) {
                $calls['cb1']++;
            };
            $cb2 = function () use (&$calls) {
                $calls['cb2']++;
            };
            $cb3 = function () use (&$calls) {
                $calls['cb3']++;
            };

            await($subscriber->subscribe('shared_channel', $cb1));
            await($subscriber->subscribe('shared_channel', $cb2));
            await($subscriber->subscribe('shared_channel', $cb3));

            await($client->publish('shared_channel', 'Message 1'));
            await(delay(0.05));

            await($subscriber->unsubscribe('shared_channel', $cb2));

            await($client->publish('shared_channel', 'Message 2'));
            await(delay(0.05));

            expect($calls['cb1'])->toBe(2)
                ->and($calls['cb2'])->toBe(1)
                ->and($calls['cb3'])->toBe(2)
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('triggers multiple pattern callbacks if patterns overlap', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $receivedWide = [];
            $receivedNarrow = [];

            await($subscriber->psubscribe('sports.*', function ($pattern, $channel, $payload) use (&$receivedWide) {
                $receivedWide[] = $payload;
            }));

            await($subscriber->psubscribe('sports.basketball.*', function ($pattern, $channel, $payload) use (&$receivedNarrow) {
                $receivedNarrow[] = $payload;
            }));

            await($client->publish('sports.football.scores', 'Touchdown'));
            await($client->publish('sports.basketball.nba', 'Slam Dunk'));

            await(delay(0.05));

            expect($receivedWide)->toBe(['Touchdown', 'Slam Dunk'])
                ->and($receivedNarrow)->toBe(['Slam Dunk'])
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('triggers both standard and pattern callbacks simultaneously', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $channelReceived = [];
            $patternReceived = [];

            await($subscriber->subscribe('alerts.system', function ($channel, $payload) use (&$channelReceived) {
                $channelReceived[] = $payload;
            }));

            await($subscriber->psubscribe('alerts.*', function ($pattern, $channel, $payload) use (&$patternReceived) {
                $patternReceived[] = $payload;
            }));

            await($client->publish('alerts.system', 'Critical Failure'));
            await(delay(0.05));

            expect($channelReceived)->toBe(['Critical Failure'])
                ->and($patternReceived)->toBe(['Critical Failure'])
            ;
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('handles rapid sequential subscribe and unsubscribe cleanly', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            $promise = $subscriber->subscribe('rapid_channel', function ($channel, $payload) use (&$received) {
                $received[] = $payload;
            });

            $promise->cancel();

            await(delay(0.05));

            await($client->publish('rapid_channel', 'Should not arrive'));
            await(delay(0.05));

            expect($received)->toBeEmpty();
        } finally {
            $client->close();
            await($subscriber->close());
        }
    });

    it('safely drops messages if subscriber is closed mid-flight', function () {
        $client = new RedisClient(getConfig());
        $subscriber = await($client->createSubscriber());

        try {
            $received = [];

            await($subscriber->subscribe('close_test', function ($channel, $payload) use (&$received) {
                $received[] = $payload;
            }));

            $client->publish('close_test', 'Dropped message');
            await($subscriber->close());

            await(delay(0.05));

            expect($received)->toBeEmpty();
        } finally {
            $client->close();
        }
    });
});
