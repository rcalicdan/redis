<?php

declare(strict_types=1);

use Hibla\Promise\Promise;
use Hibla\Redis\Command\BlpopCommand;
use Hibla\Redis\Command\GetCommand;
use Hibla\Redis\Command\PingCommand;
use Hibla\Redis\Command\SetCommand;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Internals\Connection;

use function Hibla\await;
use function Hibla\delay;

it('connects to redis and executes ping', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        expect($connection->getState())->toBe(ConnectionState::READY);

        $response = await($connection->enqueue(new PingCommand()));
        expect($response)->toBe('PONG');
    } finally {
        $connection->close();
    }
});

it('can pipeline multiple commands seamlessly', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $promise1 = $connection->enqueue(new SetCommand(['test_pipeline', 'hello_world']));
        $promise2 = $connection->enqueue(new GetCommand(['test_pipeline']));
        $promise3 = $connection->enqueue(new PingCommand(['ALIVE']));

        $results = await(Promise::all([$promise1, $promise2, $promise3]));

        expect($results[0])->toBe('OK')
            ->and($results[1])->toBe('hello_world')
            ->and($results[2])->toBe('ALIVE')
        ;
    } finally {
        $connection->close();
    }
});

it('forcefully closes connection when a blocking command is cancelled mid-flight', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $promise = $connection->enqueue(new BlpopCommand(['empty_list', 0]));
        await(delay(0.05));

        $promise->cancel();
        expect($connection->isClosed())->toBeTrue();
    } finally {
        $connection->close();
    }
});

it('safely removes a command from the queue if cancelled before being flushed', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $promise = $connection->enqueue(new PingCommand(['DEAD']));
        $promise->cancel();
        $response = await($connection->enqueue(new PingCommand(['ALIVE'])));

        expect($response)->toBe('ALIVE')
            ->and($connection->isClosed())->toBeFalse()
        ;
    } finally {
        $connection->close();
    }
});

it('rejects pending promises when the connection is closed abruptly', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $connection->enqueue(new BlpopCommand(['empty_list', 0]));
        $pendingPromise = $connection->enqueue(new GetCommand(['some_key']));
        $connection->close();

        try {
            await($pendingPromise);
            $this->fail('Expected ConnectionException to be thrown');
        } catch (ConnectionException $e) {
            expect($e->getMessage())->toBe('Connection was closed');
        }
    } finally {
        $connection->close();
    }
});

it('connects to redis over TLS and executes ping', function () {
    $config = getSslConfig();
    $connection = await(Connection::create($config));

    try {
        expect($connection->getState())->toBe(ConnectionState::READY);

        $response = await($connection->enqueue(new PingCommand(['SECURE_PONG'])));
        expect($response)->toBe('SECURE_PONG');
    } finally {
        $connection->close();
    }
})->skipOnWindows();

it('rejects connection if strict SSL verification is enabled with self-signed cert', function () {
    $config = getSslConfig(['ssl_verify' => true, 'ssl_ca' => null]);

    try {
        await(Connection::create($config));
        $this->fail('Expected ConnectionException to be thrown due to strict SSL verification');
    } catch (ConnectionException $e) {
        expect($e->getMessage())->toContain('Failed to connect to Redis');
    }
})->skipOnWindows();

it('fails to connect when talking plaintext to a TLS port', function () {
    $config = getConfig(['port' => getenv('REDIS_SSL_PORT') !== false ? (int) getenv('REDIS_SSL_PORT') : 6380]);

    try {
        $connection = await(Connection::create($config));
        await($connection->enqueue(new PingCommand()));
        $this->fail('Expected ConnectionException to be thrown');
    } catch (ConnectionException $e) {
        expect(true)->toBeTrue();
    }
})->skipOnWindows();
