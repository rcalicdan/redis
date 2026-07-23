<?php

declare(strict_types=1);

namespace Hibla\Redis;

use Hibla\Promise\Exceptions\CancelledException;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\BlpopCommand;
use Hibla\Redis\Command\DelCommand;
use Hibla\Redis\Command\ExecCommand;
use Hibla\Redis\Command\GetCommand;
use Hibla\Redis\Command\HgetallCommand;
use Hibla\Redis\Command\MgetCommand;
use Hibla\Redis\Command\MultiCommand;
use Hibla\Redis\Command\PingCommand;
use Hibla\Redis\Command\PublishCommand;
use Hibla\Redis\Command\SetCommand;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Interfaces\RedisClientInterface;
use Hibla\Redis\Internals\Connection;
use Hibla\Redis\Internals\Pipeline;
use Hibla\Redis\Internals\RedisSubscriber;
use Hibla\Redis\Internals\RedisTransaction;
use Hibla\Redis\Internals\TransactionExecutionState;
use Hibla\Redis\Manager\PoolManager;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Interfaces\ConnectorInterface;

use function Hibla\async;
use function Hibla\await;

final class RedisClient implements RedisClientInterface
{
    private ?PoolManager $pool = null;

    private bool $isClosing = false;

    /**
     * @var PromiseInterface<void>|null
     */
    private ?PromiseInterface $closePromise = null;

    /**
     * @var RedisConfig|array<string, mixed>|string
     */
    private RedisConfig|array|string $config;

    /**
     * @param RedisConfig|array<string, mixed>|string $config
     */
    public function __construct(
        RedisConfig|array|string $config,
        int $minConnections = 0,
        int $maxConnections = 10,
        int $idleTimeout = 60,
        int $maxLifetime = 3600,
        int $maxWaiters = 0,
        float $acquireTimeout = 10.0,
        ?ConnectorInterface $connector = null
    ) {
        $this->config = $config;

        $this->pool = new PoolManager(
            config: $config,
            maxSize: $maxConnections,
            minSize: $minConnections,
            idleTimeout: $idleTimeout,
            maxLifetime: $maxLifetime,
            maxWaiters: $maxWaiters,
            acquireTimeout: $acquireTimeout,
            connector: $connector
        );
    }

    /**
     * {@inheritDoc}
     */
    public array $stats {
        get {
            if ($this->pool === null) {
                return [];
            }

            return $this->pool->stats;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @template TReturn
     *
     * @param CommandInterface<TReturn> $command
     *
     * @return PromiseInterface<TReturn>
     */
    public function executeCommand(CommandInterface $command): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pool = $this->pool;
        $connection = null;

        /** @var Promise<TReturn> $outerPromise */
        $outerPromise = new Promise();

        $poolPromise = $pool->get();

        $poolPromise->then(function (Connection $conn) use ($command, &$connection, $outerPromise, $pool): void {
            $connection = $conn;

            if ($outerPromise->isCancelled()) {
                $pool->release($connection);

                return;
            }

            $innerPromise = $conn->enqueue($command);

            $innerPromise->then(
                function (mixed $result) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->resolve($result);
                    }
                },
                function (\Throwable $e) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->reject($e);
                    }
                }
            );

            $outerPromise->onCancel(function () use ($innerPromise): void {
                if (! $innerPromise->isSettled()) {
                    $innerPromise->cancel();
                }
            });
        }, function (\Throwable $e) use ($outerPromise): void {
            if (! $outerPromise->isSettled()) {
                $outerPromise->reject($e);
            }
        });

        $outerPromise->finally(function () use ($pool, &$connection): void {
            if ($connection !== null) {
                $pool->release($connection);
            }
        });

        $outerPromise->onCancel(function () use ($poolPromise): void {
            if (! $poolPromise->isSettled()) {
                $poolPromise->cancel();
            }
        });

        return $outerPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function pipeline(callable $callback): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pipeline = new Pipeline();

        $callback($pipeline);

        $pipeline->lock();
        $commands = $pipeline->commands;

        if ($commands === []) {
            return Promise::resolved([]);
        }

        $pool = $this->pool;
        $connection = null;

        /** @var Promise<array<int, mixed>> $outerPromise */
        $outerPromise = new Promise();

        $poolPromise = $pool->get();

        $poolPromise->then(function (Connection $conn) use ($commands, &$connection, $outerPromise, $pool): void {
            $connection = $conn;

            if ($outerPromise->isCancelled()) {
                $pool->release($connection);

                return;
            }

            $innerPromise = $conn->enqueueBatch($commands);

            $innerPromise->then(
                function (array $results) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->resolve($results);
                    }
                },
                function (\Throwable $e) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->reject($e);
                    }
                }
            );

            $outerPromise->onCancel(function () use ($innerPromise): void {
                if (! $innerPromise->isSettled()) {
                    $innerPromise->cancel();
                }
            });
        }, function (\Throwable $e) use ($outerPromise): void {
            if (! $outerPromise->isSettled()) {
                $outerPromise->reject($e);
            }
        });

        $outerPromise->finally(function () use ($pool, &$connection): void {
            if ($connection !== null) {
                $pool->release($connection);
            }
        });

        $outerPromise->onCancel(function () use ($poolPromise): void {
            if (! $poolPromise->isSettled()) {
                $poolPromise->cancel();
            }
        });

        return $outerPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function createSubscriber(float $minReconnectInterval = 1.0, float $maxReconnectInterval = 30.0): PromiseInterface
    {
        $subscriber = new RedisSubscriber(
            $this->config,
            $minReconnectInterval,
            $maxReconnectInterval
        );

        $promise = $subscriber->initialize()->then(function () use ($subscriber) {
            return $subscriber;
        });

        $promise->onCancel($subscriber->close(...));

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritDoc}
     *
     * @template TResult
     *
     * @param callable(Interfaces\RedisTransactionInterface): TResult $callback
     *
     * @return PromiseInterface<TResult>
     */
    public function transaction(callable $callback): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pool = $this->pool;
        $state = new TransactionExecutionState();

        $fiberPromise = async(function () use ($callback, $pool, $state) {
            if ($state->isCancelled) {
                return null;
            }

            try {
                $state->poolPromise = $pool->get();
                /** @var Connection $conn */
                $conn = await($state->poolPromise);
                $state->poolPromise = null;

                // @phpstan-ignore-next-line if.alwaysFalse
                if ($state->isCancelled) {
                    $pool->release($conn);

                    return null;
                }

                $state->activeTx = new RedisTransaction($conn, $pool);

                $state->innerWorkPromise = async(fn() => $callback($state->activeTx));
                $result = await($state->innerWorkPromise);

                if ($state->activeTx->isInMulti()) {
                    $execResult = await($state->activeTx->exec());

                    /** @var TResult $execResult */
                    return $execResult; // @phpstan-ignore varTag.type
                }

                return $result;
            } catch (\Throwable $e) {
                // @phpstan-ignore-next-line if.alwaysFalse
                if ($state->isCancelled) {
                    return null;
                }

                if (
                    $e instanceof CancelledException
                    && $state->innerWorkPromise !== null
                    && ! $state->innerWorkPromise->isSettled()
                ) {
                    $state->innerWorkPromise->cancel();
                }

                if ($state->activeTx !== null && $state->activeTx->isActive()) {
                    try {
                        $state->activeTx->forceCancelCurrentQuery();
                        await($state->activeTx->abort());
                    } catch (\Throwable) {
                        // Ignore cleanup failure and the original exception takes precedence
                    }
                }

                throw $e;
            } finally {
                if ($state->activeTx !== null) {
                    $state->activeTx->release();
                    $state->activeTx = null;
                }
            }
        });

        $fiberPromise->onCancel(function () use ($state): void {
            $state->isCancelled = true;

            if ($state->poolPromise !== null && ! $state->poolPromise->isSettled()) {
                $state->poolPromise->cancel();
            }

            if ($state->innerWorkPromise !== null && ! $state->innerWorkPromise->isSettled()) {
                $state->innerWorkPromise->cancel();
            }

            if ($state->activeTx !== null && $state->activeTx->isActive()) {
                $state->activeTx->forceCancelCurrentQuery();
            }
        });

        /** @var PromiseInterface<TResult> $resultPromise */
        $resultPromise = Promise::propagateCancellation($fiberPromise);

        return $resultPromise;
    }

    /**
     * Executes multiple commands inside a MULTI/EXEC block in a single TCP write.
     * Perfect for atomic "fire and forget" operations that do not require WATCH.
     *
     * @param callable(Interfaces\PipelineInterface): void $callback
     * @return PromiseInterface<array<int, mixed>> Resolves to the array of EXEC results.
     */
    public function atomic(callable $callback): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        $pipeline = new Pipeline();
        $callback($pipeline);

        $pipeline->lock();
        $commands = $pipeline->commands;

        if ($commands === []) {
            return Promise::resolved([]);
        }

        $wrappedCommands = [new MultiCommand(), ...$commands, new ExecCommand()];

        /** @var Promise<array<int, mixed>> $outerPromise */
        $outerPromise = new Promise();

        $pool = $this->pool;
        $connection = null;

        $poolPromise = $pool->get();
        $poolPromise->then(function (Internals\Connection $conn) use ($commands, $wrappedCommands, &$connection, $outerPromise, $pool): void {
            $connection = $conn;

            if ($outerPromise->isCancelled()) {
                $pool->release($connection);
                return;
            }

            $innerPromise = $conn->enqueueBatch($wrappedCommands);

            $innerPromise->then(
                function (array $results) use ($commands, $outerPromise): void {
                    $execResults = array_pop($results);

                    if ($execResults instanceof \Throwable) {
                        if (! $outerPromise->isSettled()) {
                            $outerPromise->reject($execResults);
                        }
                        return;
                    }

                    if (! \is_array($execResults)) {
                        if (! $outerPromise->isSettled()) {
                            $outerPromise->resolve($execResults ?? []);
                        }
                        return;
                    }

                    // Map the raw Redis responses through the specific command parsers
                    $formatted = [];
                    foreach ($execResults as $i => $raw) {
                        if ($raw instanceof \Throwable) {
                            $formatted[$i] = $raw; // Keep Redis execution errors as Throwables
                        } elseif (isset($commands[$i])) {
                            $formatted[$i] = $commands[$i]->parseResponse($raw);
                        } else {
                            $formatted[$i] = $raw;
                        }
                    }

                    if (! $outerPromise->isSettled()) {
                        $outerPromise->resolve($formatted);
                    }
                },
                function (\Throwable $e) use ($outerPromise): void {
                    if (! $outerPromise->isSettled()) {
                        $outerPromise->reject($e);
                    }
                }
            );

            $outerPromise->onCancel(function () use ($innerPromise): void {
                if (! $innerPromise->isSettled()) {
                    $innerPromise->cancel();
                }
            });
        }, function (\Throwable $e) use ($outerPromise): void {
            if (! $outerPromise->isSettled()) {
                $outerPromise->reject($e);
            }
        });

        $outerPromise->finally(function () use ($pool, &$connection): void {
            if ($connection !== null) {
                $pool->release($connection);
            }
        });

        $outerPromise->onCancel(function () use ($poolPromise): void {
            if (! $poolPromise->isSettled()) {
                $poolPromise->cancel();
            }
        });

        return $outerPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(string $channel, string $message): PromiseInterface
    {
        return $this->executeCommand(new PublishCommand([$channel, $message]));
    }

    /**
     * {@inheritDoc}
     */
    public function healthCheck(): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::rejected(new ConnectionException('Client is closed'));
        }

        return $this->pool->healthCheck();
    }

    /**
     * {@inheritDoc}
     */
    public function ping(?string $message = null): PromiseInterface
    {
        $args = $message === null ? [] : [$message];

        return $this->executeCommand(new PingCommand($args));
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): PromiseInterface
    {
        return $this->executeCommand(new GetCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): PromiseInterface
    {
        return $this->executeCommand(new SetCommand([$key, $value]));
    }

    /**
     * {@inheritDoc}
     */
    public function del(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new DelCommand($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function mget(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new MgetCommand($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function hgetall(string $key): PromiseInterface
    {
        return $this->executeCommand(new HgetallCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface
    {
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;

        return $this->executeCommand(new BlpopCommand($args));
    }

    /**
     * {@inheritDoc}
     */
    public function closeAsync(float $timeout = 0.0): PromiseInterface
    {
        if ($this->pool === null) {
            return Promise::resolved();
        }

        if ($this->closePromise !== null) {
            return $this->closePromise;
        }

        $pool = $this->pool;

        $this->closePromise = $pool->closeAsync($timeout)
            ->then(function (): void {
                if ($this->isClosing) {
                    return;
                }

                $this->pool = null;
                $this->closePromise = null;
            });

        return $this->closePromise;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->pool === null) {
            return;
        }

        $this->isClosing = true;

        $this->pool->close();
        $this->pool = null;
        $this->closePromise = null;

        $this->isClosing = false;
    }

    public function __destruct()
    {
        $this->close();
    }
}
