<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\AuthCommand;
use Hibla\Redis\Command\SelectCommand;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Handlers\CommandHandler;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Connector;
use Hibla\Socket\Interfaces\ConnectionInterface as SocketConnection;
use Hibla\Socket\Interfaces\ConnectorInterface;
use LogicException;
use SplQueue;
use Throwable;

/**
 * @internal
 */
final class Connection
{
    private readonly ConnectionContext $ctx;

    private readonly CommandHandler $commandHandler;

    public function __construct(
        private readonly RedisConfig $config,
        private readonly ?ConnectorInterface $connector = null
    ) {
        $this->ctx = new ConnectionContext();
        $this->commandHandler = new CommandHandler($this->ctx);
    }

    /**
     * @return PromiseInterface<self>
     */
    public static function create(RedisConfig $config, ?ConnectorInterface $connector = null): PromiseInterface
    {
        $connection = new self($config, $connector);

        return $connection->connect();
    }

    /**
     * @return PromiseInterface<self>
     */
    public function connect(): PromiseInterface
    {
        if ($this->ctx->state !== ConnectionState::DISCONNECTED) {
            return Promise::rejected(new LogicException('Connection is already active'));
        }

        $this->ctx->state = ConnectionState::CONNECTING;

        /** @var Promise<self> $connectPromise */
        $connectPromise = new Promise();
        $this->ctx->connectPromise = $connectPromise;

        $connector = $this->connector ?? new Connector([
            'tcp' => true,
            'tls' => $this->config->ssl,
            'timeout' => $this->config->connectTimeout,
        ]);

        $uri = ($this->config->ssl ? 'tls://' : 'tcp://') . $this->config->host . ':' . $this->config->port;

        $connector->connect($uri)->then(
            $this->handleSocketConnected(...),
            function (Throwable $e) use ($connectPromise): void {
                $this->ctx->state = ConnectionState::CLOSED;
                $connectPromise->reject(
                    new ConnectionException('Failed to connect to Redis: ' . $e->getMessage(), (int) $e->getCode(), $e)
                );
            }
        );

        return $connectPromise;
    }

    public function enqueue(CommandInterface $command): PromiseInterface
    {
        if ($this->ctx->state === ConnectionState::CLOSED) {
            return Promise::rejected(new ConnectionException('Connection is closed'));
        }

        /** @var Promise<mixed> $promise */
        $promise = new Promise();
        $request = new CommandRequest($promise, $command);

        $this->ctx->writeQueue->enqueue($request);
        $this->commandHandler->flush();

        $promise->onCancel(function () use ($request): void {
            $this->handleCommandCancellation($request);
        });

        return $promise;
    }

    public function close(): void
    {
        if ($this->ctx->state === ConnectionState::CLOSED) {
            return;
        }

        $this->ctx->state = ConnectionState::CLOSED;

        if ($this->ctx->socket !== null) {
            $this->ctx->socket->close();
            $this->ctx->socket = null;
        }

        $this->commandHandler->failPending(new ConnectionException('Connection was closed'));
    }

    public function getState(): ConnectionState
    {
        return $this->ctx->state;
    }

    public function isClosed(): bool
    {
        return $this->ctx->state === ConnectionState::CLOSED;
    }

    private function handleCommandCancellation(CommandRequest $request): void
    {
        if ($this->removeFromQueue($request)) {
            return;
        }

        if ($request->command->isBlocking() && $this->ctx->state !== ConnectionState::CLOSED) {
            $this->close();
        }
    }

    private function removeFromQueue(CommandRequest $command): bool
    {
        $found = false;
        /** @var SplQueue<CommandRequest> $temp */
        $temp = new SplQueue();

        while (! $this->ctx->writeQueue->isEmpty()) {
            $cmd = $this->ctx->writeQueue->dequeue();
            if ($cmd === $command) {
                $found = true;
            } else {
                $temp->enqueue($cmd);
            }
        }
        $this->ctx->writeQueue = $temp;

        return $found;
    }

    private function handleSocketConnected(SocketConnection $socket): void
    {
        if ($this->ctx->state !== ConnectionState::CONNECTING) {
            $socket->close();

            return;
        }

        $this->ctx->socket = $socket;
        $this->ctx->socket->on('data', $this->commandHandler->handleData(...));
        $this->ctx->socket->on('close', $this->close(...));
        $this->ctx->socket->on('error', function (Throwable $e): void {
            $this->commandHandler->failPending(new ConnectionException('Socket error: ' . $e->getMessage()));
            $this->close();
        });

        $this->ctx->state = ConnectionState::READY;

        $initPromises = [];

        if ($this->config->password !== '') {
            $authArgs = $this->config->username !== ''
                ? [$this->config->username, $this->config->password]
                : [$this->config->password];

            $initPromises[] = $this->enqueue(new AuthCommand($authArgs));
        }

        if ($this->config->database !== 0) {
            $initPromises[] = $this->enqueue(new SelectCommand([$this->config->database]));
        }

        if ($initPromises === []) {
            $this->resolveConnectionPromise();
        } else {
            Promise::all($initPromises)->then(
                $this->resolveConnectionPromise(...),
                function (Throwable $e): void {
                    $this->close();
                    if ($this->ctx->connectPromise !== null) {
                        $this->ctx->connectPromise->reject(
                            new ConnectionException('Redis initialization failed: ' . $e->getMessage())
                        );
                    }
                }
            );
        }
    }

    private function resolveConnectionPromise(): void
    {
        if ($this->ctx->connectPromise !== null) {
            $promise = $this->ctx->connectPromise;
            $this->ctx->connectPromise = null;
            $promise->resolve($this);

            $this->commandHandler->flush();
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
