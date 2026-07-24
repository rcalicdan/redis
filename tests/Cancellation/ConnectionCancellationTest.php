<?php

declare(strict_types=1);

use Hibla\EventLoop\Loop;
use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Internals\Connection;

use function Hibla\await;
use function Hibla\delay;

it('ignores responses for cancelled non-blocking commands mid-flight', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $promise1 = $connection->enqueue(new PingCommand(['IGNORED']));

        $promise1->cancel();

        $promise2 = $connection->enqueue(new PingCommand(['KEPT']));
        $result = await($promise2);

        expect($result)->toBe('KEPT')
            ->and($promise1->isCancelled())->toBeTrue()
            ->and($connection->isClosed())->toBeFalse()
        ;
    } finally {
        $connection->close();
    }
});

it('forcefully closes the connection if a blocking command is cancelled mid-flight', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $promise = $connection->enqueue(new BlpopCommand(['empty_list', 0]));
        await(delay(0.01));
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue()
            ->and($connection->isClosed())->toBeTrue()
        ;
    } finally {
        $connection->close();
    }
});

it('correctly routes responses when a pipelined command is cancelled', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $p1 = $connection->enqueue(new PingCommand(['ONE']));
        $p2 = $connection->enqueue(new PingCommand(['TWO']));
        $p3 = $connection->enqueue(new PingCommand(['THREE']));

        $p2->cancel();
        $r1 = await($p1);
        $r3 = await($p3);

        expect($r1)->toBe('ONE')
            ->and($r3)->toBe('THREE')
            ->and($p2->isCancelled())->toBeTrue()
            ->and($p1->isFulfilled())->toBeTrue()
            ->and($p3->isFulfilled())->toBeTrue()
        ;
    } finally {
        $connection->close();
    }
});

it('aborts cleanly if connection creation itself is cancelled', function () {
    $config = getConfig();
    $connectPromise = Connection::create($config);
    $connectPromise->cancel();

    expect($connectPromise->isCancelled())->toBeTrue();

    await(delay(0.01));
});

it('safely removes a command from the writeQueue if cancelled before connection is established', function () {
    $config = getConfig();
    $connection = new Connection($config);
    $connectPromise = $connection->connect();
    $commandPromise = $connection->enqueue(new PingCommand(['BEFORE_CONNECT']));

    $commandPromise->cancel();
    await($connectPromise);

    try {
        expect($commandPromise->isCancelled())->toBeTrue();
        $response = await($connection->enqueue(new PingCommand(['HEALTHY'])));
        expect($response)->toBe('HEALTHY');
    } finally {
        $connection->close();
    }
});

it('rejects enqueued commands if the connection attempt itself is cancelled', function () {
    $config = getConfig();
    $connection = new Connection($config);

    $connectPromise = $connection->connect();
    $commandPromise = $connection->enqueue(new PingCommand());
    $connectPromise->cancel();

    expect(fn () => await($commandPromise))
        ->toThrow(ConnectionException::class, 'Connection was closed')
    ;
});

it('handles all pipelined commands being cancelled mid-flight', function () {
    $config = getConfig();
    $connection = await(Connection::create($config));

    try {
        $p1 = $connection->enqueue(new PingCommand(['ONE']));
        $p2 = $connection->enqueue(new PingCommand(['TWO']));

        $p1->cancel();
        $p2->cancel();

        await(delay(0.05));

        expect($connection->isClosed())->toBeFalse();

        $response = await($connection->enqueue(new PingCommand(['ACTIVE'])));
        expect($response)->toBe('ACTIVE');
    } finally {
        $connection->close();
    }
});

it('aborts cleanly if cancelled during the TLS handshake', function () {
    $config = getSslConfig();

    $connectPromise = Connection::create($config);

    Loop::nextTick(function () use ($connectPromise) {
        $connectPromise->cancel();
    });

    expect(fn () => await($connectPromise))
        ->toThrow(CancelledException::class)
    ;
})->skipOnWindows();
