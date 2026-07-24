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

describe('RedisClient - Core Connection & Lifecycle', function (): void {

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

    it('can perform a health check on the pool', function () {
        $client = new RedisClient(getConfig());

        try {
            await($client->ping());

            $health = await($client->healthCheck());

            expect($health['total_checked'])->toBe(1)
                ->and($health['healthy'])->toBe(1)
                ->and($health['unhealthy'])->toBe(0);
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Key Management', function (): void {

    it('can delete one or multiple keys via DEL and UNLINK', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('del_k1', 'v1'));
            await($client->set('del_k2', 'v2'));
            await($client->set('un_k1', 'v3'));

            $deletedCount = await($client->del('del_k1', 'del_k2', 'missing_key'));
            expect($deletedCount)->toBe(2);

            $unlinkedCount = await($client->unlink('un_k1'));
            expect($unlinkedCount)->toBe(1);

            expect(await($client->get('del_k1')))->toBeNull();
        } finally {
            $client->close();
        }
    });

    it('can check key existence via EXISTS', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('ex_1', 'v1'));
            await($client->set('ex_2', 'v2'));

            expect(await($client->exists('ex_1')))->toBe(1)
                ->and(await($client->exists('ex_1', 'ex_2', 'missing')))->toBe(2);
        } finally {
            $client->close();
        }
    });

    it('can manage key TTL and expiration via EXPIRE and TTL', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('ttl_k', 'v'));

            expect(await($client->ttl('ttl_k')))->toBe(-1); 

            $expireResult = await($client->expire('ttl_k', 60));
            expect($expireResult)->toBe(1);

            $ttl = await($client->ttl('ttl_k'));
            expect($ttl)->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
        } finally {
            $client->close();
        }
    });

    it('can inspect key type via TYPE', function () {
        $client = createIsolatedCleanClient();

        try {
            await($client->set('str_k', 'v'));
            await($client->hset('hash_k', 'f', 'v'));

            expect(await($client->type('str_k')))->toBe('string')
                ->and(await($client->type('hash_k')))->toBe('hash')
                ->and(await($client->type('missing_k')))->toBe('none');
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Strings & Numerics', function (): void {

    it('can SET and GET string values', function () {
        $client = createIsolatedCleanClient();

        try {
            $setResult = await($client->set('my_key', 'my_value'));
            expect($setResult)->toBe('OK');

            $getResult = await($client->get('my_key'));
            expect($getResult)->toBe('my_value');

            expect(await($client->get('does_not_exist')))->toBeNull();
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

    it('can increment and decrement numbers via INCR, DECR, INCRBY, INCRBYFLOAT', function () {
        $client = createIsolatedCleanClient();

        try {
            expect(await($client->incr('num')))->toBe(1)
                ->and(await($client->incrby('num', 5)))->toBe(6)
                ->and(await($client->decr('num')))->toBe(5)
                ->and(await($client->incrbyfloat('float_num', 2.5)))->toBe(2.5);
        } finally {
            $client->close();
        }
    });

    it('can set key with expiration via SETEX', function () {
        $client = createIsolatedCleanClient();

        try {
            expect(await($client->setex('setex_key', 30, 'setex_val')))->toBe('OK')
                ->and(await($client->get('setex_key')))->toBe('setex_val')
                ->and(await($client->ttl('setex_key')))->toBeGreaterThan(0);
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Hashes', function (): void {

    it('can operate on hashes via HSET, HGET, HEXISTS, HMGET, HDEL, HGETALL', function () {
        $client = createIsolatedCleanClient();

        try {
            $added = await($client->hset('user:100', 'name', 'Alice', 'role', 'admin'));
            expect($added)->toBe(2);

            expect(await($client->hget('user:100', 'name')))->toBe('Alice')
                ->and(await($client->hexists('user:100', 'role')))->toBe(1)
                ->and(await($client->hexists('user:100', 'missing')))->toBe(0);

            expect(await($client->hmget('user:100', 'name', 'missing', 'role')))->toBe(['Alice', null, 'admin']);

            $all = await($client->hgetall('user:100'));
            expect($all)->toBe([
                'name' => 'Alice',
                'role' => 'admin',
            ]);

            expect(await($client->hdel('user:100', 'role')))->toBe(1)
                ->and(await($client->hget('user:100', 'role')))->toBeNull();
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
});

describe('RedisClient - Lists', function (): void {

    it('can push, pop, and inspect lists via LPUSH, RPUSH, LLEN, LPOP, RPOP', function () {
        $client = createIsolatedCleanClient();

        try {
            expect(await($client->lpush('queue', 'job2', 'job1')))->toBe(2); 
            expect(await($client->rpush('queue', 'job3')))->toBe(3);    

            expect(await($client->llen('queue')))->toBe(3);

            expect(await($client->lpop('queue')))->toBe('job1');
            expect(await($client->rpop('queue')))->toBe('job3');
            expect(await($client->llen('queue')))->toBe(1);
        } finally {
            $client->close();
        }
    });

    it('can execute BLPOP and block the connection until item arrives', function () {
        $client = createIsolatedCleanClient();

        try {
            Loop::addTimer(0.1, function () {
                $pusher = new RedisClient(getConfig());
                $pusher->lpush('my_list', 'popped_value')->finally(fn () => $pusher->close());
            });

            $start = microtime(true);

            $result = await($client->blpop('my_list', 0));
            $elapsed = microtime(true) - $start;

            expect($result)->toBe(['my_list', 'popped_value'])
                ->and($elapsed)->toBeGreaterThanOrEqual(0.09);
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Sets', function (): void {

    it('can perform set operations via SADD, SISMEMBER, SMEMBERS, SREM', function () {
        $client = createIsolatedCleanClient();

        try {
            expect(await($client->sadd('tags', 'php', 'async', 'redis', 'php')))->toBe(3);

            expect(await($client->sismember('tags', 'php')))->toBe(1)
                ->and(await($client->sismember('tags', 'python')))->toBe(0);

            $members = await($client->smembers('tags'));
            sort($members);
            expect($members)->toBe(['async', 'php', 'redis']);

            expect(await($client->srem('tags', 'async')))->toBe(1)
                ->and(await($client->sismember('tags', 'async')))->toBe(0);
        } finally {
            $client->close();
        }
    });
});

describe('RedisClient - Sorted Sets (ZSets)', function (): void {

    it('can perform sorted set operations via ZADD, ZSCORE, ZRANGE, ZREM', function () {
        $client = createIsolatedCleanClient();

        try {
            expect(await($client->zadd('leaderboard', 100, 'player1', 250, 'player2')))->toBe(2);

            expect(await($client->zscore('leaderboard', 'player1')))->toBe('100')
                ->and(await($client->zscore('leaderboard', 'missing')))->toBeNull();

            expect(await($client->zrange('leaderboard', 0, -1)))->toBe(['player1', 'player2']);

            expect(await($client->zrem('leaderboard', 'player1')))->toBe(1)
                ->and(await($client->zrange('leaderboard', 0, -1)))->toBe(['player2']);
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

            /** @var array $results */
            $results = await(Promise::all($promises));

            expect($results)->toBe(['A', 'B', 'C', 'D', 'E'])
                ->and($client->stats['total_connections'])->toBeLessThanOrEqual(3);
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

        /** @var array $results */
        $results = await(Promise::all([$p1, $shutdown]));

        expect($results[0])->toBe('First')
            ->and($client->stats)->toBeEmpty();
    });

    it('rejects commands submitted while closeAsync is pending', function () {
        $client = new RedisClient(getConfig());

        $client->closeAsync();

        expect(fn () => await($client->ping()))
            ->toThrow(PoolException::class, 'Pool is shutting down');
    });

    it('rejects commands submitted after close is called', function () {
        $client = new RedisClient(getConfig());
        $client->close();

        expect(fn () => await($client->ping()))
            ->toThrow(ConnectionException::class, 'Client is closed');
    });
});