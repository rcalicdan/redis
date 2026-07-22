<?php

declare(strict_types=1);

use Hibla\Promise\Promise;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Command\PingCommand;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Internals\CommandRequest;

afterEach(function () {
    Mockery::close();
});

it('does not flush if state is not READY', function () {
    [$handler, $ctx, $socket] = createHandler();
    $ctx->state = ConnectionState::CONNECTING;

    $promise = new Promise();
    $ctx->writeQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $socket->shouldNotReceive('write');

    $handler->flush();

    expect($ctx->writeQueue->count())->toBe(1)
        ->and($ctx->responseQueue->count())->toBe(0)
    ;
});

it('flushes queued commands to the socket and moves them to responseQueue', function () {
    [$handler, $ctx, $socket] = createHandler();

    $promise = new Promise();
    $ctx->writeQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $socket->shouldReceive('write')
        ->once()
        ->with("*1\r\n$4\r\nPING\r\n")
    ;

    $handler->flush();

    expect($ctx->writeQueue->isEmpty())->toBeTrue()
        ->and($ctx->responseQueue->count())->toBe(1)
    ;
});

it('skips cancelled promises during flush', function () {
    [$handler, $ctx, $socket] = createHandler();

    $promise1 = new Promise();
    $promise1->cancel();

    $promise2 = new Promise();

    $ctx->writeQueue->enqueue(new CommandRequest($promise1, new PingCommand(['CANCELLED'])));
    $ctx->writeQueue->enqueue(new CommandRequest($promise2, new PingCommand(['ALIVE'])));

    $socket->shouldReceive('write')
        ->once()
        ->with("*2\r\n$4\r\nPING\r\n$5\r\nALIVE\r\n")
    ;

    $handler->flush();

    expect($ctx->writeQueue->isEmpty())->toBeTrue()
        ->and($ctx->responseQueue->count())->toBe(1)
        ->and($ctx->responseQueue->dequeue()->promise)->toBe($promise2)
    ;
});

it('parses response data and resolves the promise', function () {
    [$handler, $ctx] = createHandler();

    $promise = new Promise();
    $request = new CommandRequest($promise, new PingCommand());

    $ctx->responseQueue->enqueue($request);

    $handler->handleData("+PONG\r\n");

    expect($promise->isFulfilled())->toBeTrue()
        ->and($promise->value)->toBe('PONG')
        ->and($ctx->responseQueue->isEmpty())->toBeTrue()
    ;
});

it('rejects the promise if Redis returns an error', function () {
    [$handler, $ctx] = createHandler();

    $promise = new Promise();
    $request = new CommandRequest($promise, new PingCommand());

    $ctx->responseQueue->enqueue($request);

    $handler->handleData("-WRONGTYPE Operation against a key holding the wrong kind of value\r\n");

    expect($promise->isRejected())->toBeTrue()
        ->and($promise->reason)->toBeInstanceOf(RedisException::class)
        ->and($promise->reason->getMessage())->toBe('WRONGTYPE Operation against a key holding the wrong kind of value')
    ;
});

it('waits for incomplete chunks before resolving', function () {
    [$handler, $ctx] = createHandler();

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $handler->handleData('+PO');
    expect($promise->isPending())->toBeTrue();

    $handler->handleData("NG\r\n");
    expect($promise->isFulfilled())->toBeTrue()
        ->and($promise->value)->toBe('PONG')
    ;
});

it('rejects the promise if command parseResponse throws an exception', function () {
    [$handler, $ctx] = createHandler();

    $brokenCommand = new class () extends AbstractCommand {
        public string $id = 'BROKEN';

        public function parseResponse(mixed $data): mixed
        {
            throw new RuntimeException('Parse error injected');
        }
    };

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, $brokenCommand));

    $handler->handleData("+OK\r\n");

    expect($promise->isRejected())->toBeTrue()
        ->and($promise->reason)->toBeInstanceOf(RuntimeException::class)
        ->and($promise->reason->getMessage())->toBe('Parse error injected')
    ;
});

it('drops responses for cancelled mid-flight promises', function () {
    [$handler, $ctx] = createHandler();

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $promise->cancel();

    $handler->handleData("+PONG\r\n");

    expect($ctx->responseQueue->isEmpty())->toBeTrue()
        ->and($promise->isCancelled())->toBeTrue()
        ->and($promise->isFulfilled())->toBeFalse()
    ;
});

it('rejects all pending requests when failPending is called', function () {
    [$handler, $ctx] = createHandler();

    $promise1 = new Promise();
    $promise2 = new Promise();

    $ctx->writeQueue->enqueue(new CommandRequest($promise1, new PingCommand()));
    $ctx->responseQueue->enqueue(new CommandRequest($promise2, new PingCommand()));

    $exception = new ConnectionException('Socket disconnected');
    $handler->failPending($exception);

    expect($ctx->writeQueue->isEmpty())->toBeTrue()
        ->and($ctx->responseQueue->isEmpty())->toBeTrue()
        ->and($promise1->isRejected())->toBeTrue()
        ->and($promise1->reason)->toBe($exception)
        ->and($promise2->isRejected())->toBeTrue()
        ->and($promise2->reason)->toBe($exception)
    ;
});

it('intercepts pub/sub "message" arrays and routes them to callback without popping responseQueue', function () {
    [$handler, $ctx] = createHandler();

    $receivedMessage = null;
    $ctx->pubSubCallback = function (array $msg) use (&$receivedMessage): void {
        $receivedMessage = $msg;
    };

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));
    $payload = "*3\r\n$7\r\nmessage\r\n$4\r\nnews\r\n$5\r\nhello\r\n";
    $handler->handleData($payload);

    expect($receivedMessage)->toBe(['message', 'news', 'hello'])
        ->and($ctx->responseQueue->count())->toBe(1)
        ->and($promise->isPending())->toBeTrue()
    ;
});

it('intercepts pub/sub "pmessage" arrays and routes them to callback without popping responseQueue', function () {
    [$handler, $ctx] = createHandler();

    $receivedMessage = null;
    $ctx->pubSubCallback = function (array $msg) use (&$receivedMessage): void {
        $receivedMessage = $msg;
    };

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $payload = "*4\r\n$8\r\npmessage\r\n$6\r\nnews.*\r\n$10\r\nnews.sport\r\n$5\r\nhello\r\n";
    $handler->handleData($payload);

    expect($receivedMessage)->toBe(['pmessage', 'news.*', 'news.sport', 'hello'])
        ->and($ctx->responseQueue->count())->toBe(1)
        ->and($promise->isPending())->toBeTrue()
    ;
});

it('allows subscription acknowledgments to resolve pending responseQueue commands', function () {
    [$handler, $ctx] = createHandler();

    $callbackFired = false;
    $ctx->pubSubCallback = function () use (&$callbackFired): void {
        $callbackFired = true;
    };

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $payload = "*3\r\n$9\r\nsubscribe\r\n$4\r\nnews\r\n:1\r\n";
    $handler->handleData($payload);

    expect($callbackFired)->toBeFalse()
        ->and($ctx->responseQueue->isEmpty())->toBeTrue()
        ->and($promise->isFulfilled())->toBeTrue()
        ->and($promise->value)->toBe(['subscribe', 'news', 1])
    ;
});

it('ignores pub/sub messages cleanly if pubSubCallback is not set', function () {
    [$handler, $ctx] = createHandler();
    $ctx->pubSubCallback = null;

    $promise = new Promise();
    $ctx->responseQueue->enqueue(new CommandRequest($promise, new PingCommand()));

    $payload = "*3\r\n$7\r\nmessage\r\n$4\r\nnews\r\n$5\r\nhello\r\n";
    $handler->handleData($payload);

    expect($ctx->responseQueue->count())->toBe(1)
        ->and($promise->isPending())->toBeTrue()
    ;
});