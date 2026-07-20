<?php

declare(strict_types=1);

use Hibla\EventLoop\Loop;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Exceptions\PoolException;
use Hibla\Redis\RedisClient;

use function Hibla\await;

function createIsolatedCleanClient(int $maxConnections = 10): RedisClient
{
    $client = new RedisClient(getConfig(), maxConnections: $maxConnections);

    $flushCommand = new class ([]) extends AbstractCommand {
        public string $id = 'FLUSHDB';
    };

    await($client->executeCommand($flushCommand));

    return $client;
}

describe('RedisClient - Core API', function (): void {

    it('lazily initializes without making connections until requested', function () {
        $client = new RedisClient(getConfig());

        try {
            expect($client->stats['total_connections'])->toBe(0);

            await($client->ping());

            expect($client->stats['total_connections'])->toBe(1);
        } finally {
            $client->close();
        }
    });

    it('can PING the server', function () {
        $client = new RedisClient(getConfig());

        try {
            $result = await($client->ping());
            expect($result)->toBe('PONG');
        } finally {
            $client->close();
        }
    });

    it('can PING the server with a custom message', function () {
        $client = new RedisClient(getConfig());

        try {
            $result = await($client->ping('Hello Redis'));
            expect($result)->toBe('Hello Redis');
        } finally {
            $client->close();
        }
    });

    it('can SET and GET a string value', function () {
        $client = createIsolatedCleanClient();

        try {
            $setResult = await($client->set('my_key', 'my_value'));
            expect($setResult)->toBe('OK');

            $getResult = await($client->get('my_key'));
            expect($getResult)->toBe('my_value');
        } finally {
            $client->close();
        }
    });

    it('returns null when GETting a non-existent key', function () {
        $client = createIsolatedCleanClient();

        try {
            $result = await($client->get('does_not_exist_key'));
            expect($result)->toBeNull();
        } finally {
            $client->close();
        }
    });

    it('can delete one or multiple keys', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('key1', 'val1'));
            await($client->set('key2', 'val2'));
            await($client->set('key3', 'val3'));

            $deletedCount = await($client->del('key1', 'key2', 'missing_key'));
            expect($deletedCount)->toBe(2);

            expect(await($client->get('key1')))->toBeNull()
                ->and(await($client->get('key2')))->toBeNull()
                ->and(await($client->get('key3')))->toBe('val3')
            ;
        } finally {
            $client->close();
        }
    });

    it('can get multiple keys via MGET', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('m1', 'val1'));
            await($client->set('m2', 'val2'));

            $results = await($client->mget('m1', 'missing', 'm2'));

            expect($results)->toBe(['val1', null, 'val2']);
        } finally {
            $client->close();
        }
    });

    it('can retrieve a hash via HGETALL', function () {
        $client = createIsolatedCleanClient();

        try {
            $hset = new class (['my_hash', 'field1', 'val1', 'field2', 'val2']) extends AbstractCommand {
                public string $id = 'HSET';
            };
            await($client->executeCommand($hset));

            $hash = await($client->hgetall('my_hash'));

            expect($hash)->toBe([
                'field1' => 'val1',
                'field2' => 'val2',
            ]);
        } finally {
            $client->close();
        }
    });

    it('returns an empty array when HGETALL targets a missing key', function () {
        $client = createIsolatedCleanClient();

        try {
            $hash = await($client->hgetall('missing_hash'));
            expect($hash)->toBe([]);
        } finally {
            $client->close();
        }
    });

    it('can execute BLPOP and block the connection', function () {
        $client = createIsolatedCleanClient();

        try {
            Loop::addTimer(0.1, function () {
                $pusher = new RedisClient(getConfig());
                $lpush = new class (['my_list', 'popped_value']) extends AbstractCommand {
                    public string $id = 'LPUSH';
                };
                $pusher->executeCommand($lpush)->finally(fn () => $pusher->close());
            });

            $start = microtime(true);

            $result = await($client->blpop('my_list', 0));
            $elapsed = microtime(true) - $start;

            expect($result)->toBe(['my_list', 'popped_value'])
                ->and($elapsed)->toBeGreaterThanOrEqual(0.09)
            ;
        } finally {
            $client->close();
        }
    });

    it('can perform a health check on the pool', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->ping());

            $health = await($client->healthCheck());

            expect($health['total_checked'])->toBe(1)
                ->and($health['healthy'])->toBe(1)
                ->and($health['unhealthy'])->toBe(0)
            ;
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Concurrency & Pipelines', function (): void {

    it('executes concurrent commands across multiple connections safely', function () {
        $client = createIsolatedCleanClient(maxConnections: 3);

        try {
            $promises = [
                $client->ping('A'),
                $client->ping('B'),
                $client->ping('C'),
                $client->ping('D'),
                $client->ping('E'),
            ];

            $results = await(Promise::all($promises));

            expect($results)->toBe(['A', 'B', 'C', 'D', 'E'])
                ->and($client->stats['total_connections'])->toBeLessThanOrEqual(3)
            ;
        } finally {
            $client->close();
        }
    });

});

describe('RedisClient - Graceful Shutdown', function (): void {

    it('closes asynchronously, waiting for pending commands to finish', function () {
        $client = new RedisClient(getConfig());

        $p1 = $client->ping('First');
        $shutdown = $client->closeAsync();

        $results = await(Promise::all([$p1, $shutdown]));

        expect($results[0])->toBe('First')
            ->and($client->stats)->toBeEmpty()
        ;
    });

    it('rejects commands submitted while closeAsync is pending', function () {
        $client = new RedisClient(getConfig());

        $client->closeAsync();

        expect(fn () => await($client->ping()))
            ->toThrow(PoolException::class, 'Pool is shutting down')
        ;
    });

    it('rejects commands submitted after close is called', function () {
        $client = new RedisClient(getConfig());
        $client->close();

        expect(fn () => await($client->ping()))
            ->toThrow(ConnectionException::class, 'Client is closed')
        ;
    });

});
