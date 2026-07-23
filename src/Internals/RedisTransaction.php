<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\AbstractCommand;
use Hibla\Redis\Command\BlpopCommand;
use Hibla\Redis\Command\DelCommand;
use Hibla\Redis\Command\DiscardCommand;
use Hibla\Redis\Command\ExecCommand;
use Hibla\Redis\Command\GetCommand;
use Hibla\Redis\Command\HgetallCommand;
use Hibla\Redis\Command\MgetCommand;
use Hibla\Redis\Command\MultiCommand;
use Hibla\Redis\Command\PingCommand;
use Hibla\Redis\Command\SetCommand;
use Hibla\Redis\Command\UnwatchCommand;
use Hibla\Redis\Command\WatchCommand;
use Hibla\Redis\Exceptions\TransactionException;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Interfaces\RedisTransactionInterface;
use Hibla\Redis\Manager\PoolManager;

/**
 * Transaction implementation with automatic pool management, strict state checking,
 * and error-tainting.
 *
 * @internal Created by RedisClient::transaction() - do not instantiate directly.
 */
final class RedisTransaction implements RedisTransactionInterface
{
    private bool $active = true;

    private bool $released = false;

    private bool $failed = false;

    private bool $inMulti = false;

    private bool $isWatched = false;

    /**
     * @var array<int, CommandInterface<mixed>> Commands queued inside MULTI block.
     */
    private array $queuedCommands = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly PoolManager $pool
    ) {
    }

    /**
     * @template TReturn
     *
     * {@inheritDoc}
     *
     * @return PromiseInterface<TReturn>
     */
    public function executeCommand(CommandInterface $command): PromiseInterface
    {
        $this->ensureActiveAndNotFailed();

        if ($this->inMulti) {
            $this->queuedCommands[] = $command;

            $rawCmd = new class ($command->id, $command->arguments) extends AbstractCommand {
                public function __construct(public string $id, array $args)
                {
                    parent::__construct($args);
                }
            };

            $promise = $this->connection->enqueue($rawCmd);

            /** @var PromiseInterface<TReturn> $queuedPromise */
            $queuedPromise = Promise::propagateCancellation($this->trackErrorState($promise));

            return $queuedPromise;
        }

        $promise = $this->connection->enqueue($command);

        // @phpstan-ignore return.type (At runtime inside MULTI, Redis returns 'QUEUED' instead of TReturn)
        return Promise::propagateCancellation($this->trackErrorState($promise));
    }

    /**
     * {@inheritDoc}
     */
    public function watch(string ...$keys): PromiseInterface
    {
        $this->ensureActiveAndNotFailed();

        if ($this->inMulti) {
            return Promise::rejected(new TransactionException('WATCH inside MULTI is not allowed'));
        }

        $promise = $this->connection->enqueue(new WatchCommand($keys));

        $promise->then(function (): void {
            $this->isWatched = true;
        })->catch(static fn () => null);

        return Promise::propagateCancellation($this->trackErrorState($promise));
    }

    /**
     * {@inheritDoc}
     */
    public function unwatch(): PromiseInterface
    {
        $this->ensureActive();

        $promise = $this->connection->enqueue(new UnwatchCommand());

        $promise->then(function (): void {
            $this->isWatched = false;
        })->catch(static fn () => null);

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritDoc}
     */
    public function multi(): PromiseInterface
    {
        $this->ensureActiveAndNotFailed();

        if ($this->inMulti) {
            return Promise::rejected(new TransactionException('MULTI calls cannot be nested'));
        }

        $promise = $this->connection->enqueue(new MultiCommand());

        $promise->then(function (): void {
            $this->inMulti = true;
            $this->queuedCommands = [];
        })->catch(static fn () => null);

        return Promise::propagateCancellation($this->trackErrorState($promise));
    }

    /**
     * {@inheritDoc}
     */
    public function exec(): PromiseInterface
    {
        $this->ensureActive();

        if ($this->failed) {
            return Promise::rejected(
                new TransactionException(
                    'Transaction aborted due to a previous command error or cancellation. '
                        . 'Call discard() to clear the transaction state.'
                )
            );
        }

        if (! $this->inMulti) {
            return Promise::rejected(new TransactionException('EXEC without MULTI'));
        }

        $this->active = false;

        $queuedCommands = $this->queuedCommands;
        $this->queuedCommands = [];

        $promise = $this->connection->enqueue(new ExecCommand());

        /** @var PromiseInterface<array<int, mixed>|null> $execPromise */
        $execPromise = $promise->then(
            function (mixed $results) use ($queuedCommands): mixed {
                $this->inMulti = false;
                $this->isWatched = false;

                if (! \is_array($results)) {
                    return $results;
                }

                $formatted = [];
                foreach ($results as $i => $raw) {
                    if (isset($queuedCommands[$i])) {
                        $formatted[$i] = $queuedCommands[$i]->parseResponse($raw);
                    } else {
                        $formatted[$i] = $raw;
                    }
                }

                return $formatted;
            },
            function (\Throwable $e): never {
                $this->failed = true;

                throw $e;
            }
        )->finally($this->release(...));

        return Promise::uninterruptible($execPromise);
    }

    /**
     * {@inheritDoc}
     */
    public function discard(): PromiseInterface
    {
        // Idempotency guard: return resolved promise if already inactive
        if (! $this->active) {
            return Promise::resolved('OK');
        }

        if (! $this->inMulti) {
            return Promise::rejected(new TransactionException('DISCARD without MULTI'));
        }

        // Clear any hanging queries on the wire before sending DISCARD
        $this->forceCancelCurrentQuery();

        $this->active = false;
        $this->failed = false;
        $this->queuedCommands = [];

        $promise = $this->connection->enqueue(new DiscardCommand());

        $promise->then(
            function (): void {
                $this->inMulti = false;
                $this->isWatched = false;
            }
        )->finally($this->release(...));

        return Promise::uninterruptible($promise);
    }

    /**
     * {@inheritDoc}
     */
    public function ping(?string $message = null): PromiseInterface
    {
        return $this->executeCommand(new PingCommand($message === null ? [] : [$message]));
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

    public function isActive(): bool
    {
        return $this->active && ! $this->connection->isClosed();
    }

    /**
     * @internal Returns true if the transaction is currently in MULTI state.
     */
    public function isInMulti(): bool
    {
        return $this->inMulti;
    }

    /**
     * @internal Force-cancels any running query on the connection and clears the queue.
     * Called automatically before discard() or cleanup to clear the wire.
     */
    public function forceCancelCurrentQuery(): void
    {
        if (! $this->connection->isClosed()) {
            $this->connection->clearQueue();
        }
    }

    /**
     * @internal Forces the transaction to abort and clean up the connection.
     *
     * @return PromiseInterface<mixed>
     */
    public function abort(): PromiseInterface
    {
        if (! $this->active || $this->connection->isClosed()) {
            /** @var PromiseInterface<mixed> */
            return Promise::resolved();
        }

        if ($this->inMulti) {
            return $this->discard();
        }

        if ($this->isWatched) {
            $this->forceCancelCurrentQuery();
            $promise = $this->connection->enqueue(new UnwatchCommand());
            $promise->then(function (): void {
                $this->isWatched = false;
            })->catch(static fn () => null);

            return $promise;
        }

        /** @var PromiseInterface<mixed> */
        return Promise::resolved();
    }

    /**
     * @internal Safely releases the connection back to the pool.
     */
    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;
        $this->queuedCommands = [];

        if ($this->connection->isClosed()) {
            $this->pool->release($this->connection);

            return;
        }

        if ($this->inMulti) {
            $this->forceCancelCurrentQuery();
            $this->connection->enqueue(new DiscardCommand())->finally(function (): void {
                $this->pool->release($this->connection);
            });

            return;
        }

        if ($this->isWatched) {
            $this->forceCancelCurrentQuery();
            $this->connection->enqueue(new UnwatchCommand())->finally(function (): void {
                $this->pool->release($this->connection);
            });

            return;
        }

        $this->pool->release($this->connection);
    }

    /**
     * Wraps a promise so that any rejection or cancellation marks the transaction
     * as failed/tainted.
     *
     * @template T
     *
     * @param PromiseInterface<T> $promise
     *
     * @return PromiseInterface<T>
     */
    private function trackErrorState(PromiseInterface $promise): PromiseInterface
    {
        $promise->onCancel(function (): void {
            $this->failed = true;
        });

        return $promise->catch(function (\Throwable $e): never {
            $this->failed = true;

            throw $e;
        });
    }

    private function ensureActive(): void
    {
        if ($this->connection->isClosed()) {
            throw new TransactionException('Cannot perform operation: connection is closed');
        }

        if (! $this->active) {
            throw new TransactionException('Cannot perform operation: transaction is no longer active (already executed or discarded)');
        }
    }

    private function ensureActiveAndNotFailed(): void
    {
        $this->ensureActive();

        if ($this->failed) {
            throw new TransactionException(
                'Transaction aborted due to a previous command error or cancellation. '
                    . 'Call discard() to clear the transaction state.'
            );
        }
    }

    public function __destruct()
    {
        if ($this->active && ! $this->connection->isClosed() && ! $this->released) {
            $this->active = false;
            $this->release();
        } elseif (! $this->released) {
            $this->release();
        }
    }
}
