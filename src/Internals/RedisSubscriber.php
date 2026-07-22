<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\EventLoop\Loop;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;
use Hibla\Redis\Command\PsubscribeCommand;
use Hibla\Redis\Command\PunsubscribeCommand;
use Hibla\Redis\Command\SubscribeCommand;
use Hibla\Redis\Command\UnsubscribeCommand;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Interfaces\RedisSubscriberInterface;
use Hibla\Redis\ValueObjects\RedisConfig;
use InvalidArgumentException;
use Throwable;

/**
 * @internal Created via RedisClient::createSubscriber(). Do not instantiate directly.
 *
 * Bypasses the pool to maintain a persistent connection for Pub/Sub events.
 * Includes transparent auto-reconnection with exponential backoff.
 */
final class RedisSubscriber implements RedisSubscriberInterface
{
    private bool $isClosed = false;

    private ?Connection $connection = null;

    /**
     * @var PromiseInterface<Connection>|null
     */
    private ?PromiseInterface $connectionPromise = null;

    /**
     * @var array<string, list<callable(string, string): void>>
     */
    private array $channelCallbacks = [];

    /**
     * @var array<string, list<callable(string, string, string): void>>
     */
    private array $patternCallbacks = [];

    private readonly RedisConfig $config;

    /**
     * @param RedisConfig|array<string, mixed>|string $config
     */
    public function __construct(
        RedisConfig|array|string $config,
        private readonly float $minReconnectInterval = 1.0,
        private readonly float $maxReconnectInterval = 30.0,
    ) {
        $this->config = match (true) {
            $config instanceof RedisConfig => $config,
            \is_array($config) => RedisConfig::fromArray($config),
            \is_string($config) => RedisConfig::fromUri($config),
        };

        if ($this->minReconnectInterval <= 0.0) {
            throw new InvalidArgumentException('Minimum reconnect interval must be greater than 0');
        }

        if ($this->maxReconnectInterval < $this->minReconnectInterval) {
            throw new InvalidArgumentException('Maximum reconnect interval cannot be less than the minimum');
        }
    }

    /**
     * Establishes the initial connection before returning the subscriber to the user.
     *
     * @return PromiseInterface<void>
     */
    public function initialize(): PromiseInterface
    {
        $promise = $this->getConnection()->then(function (): void {
        });

        $promise->onCancel($this->close(...));

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe(string $channel, callable $callback): PromiseInterface
    {
        if ($this->isClosed) {
            return Promise::rejected(new ConnectionException('Cannot subscribe: Subscriber is closed.'));
        }

        $isFirst = ! isset($this->channelCallbacks[$channel]) || $this->channelCallbacks[$channel] === [];
        $this->channelCallbacks[$channel][] = $callback;

        if (! $isFirst) {
            return Promise::resolved();
        }

        $innerPromise = null;

        $promise = $this->getConnection()->then(function (Connection $conn) use ($channel, &$innerPromise): PromiseInterface {
            $innerPromise = $conn->enqueue(new SubscribeCommand([$channel]))->then(function (): void {
            });

            return $innerPromise;
        });

        Promise::forwardCancellation($promise, $innerPromise);

        $promise->onCancel(function () use ($channel, $callback): void {
            $this->unsubscribe($channel, $callback)->catch(static fn () => null);
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe(string $channel, ?callable $callback = null): PromiseInterface
    {
        if ($this->isClosed) {
            return Promise::resolved();
        }

        if ($callback === null) {
            unset($this->channelCallbacks[$channel]);
        } elseif (isset($this->channelCallbacks[$channel])) {
            $callbacks = $this->channelCallbacks[$channel];
            $index = array_search($callback, $callbacks, true);

            if ($index !== false) {
                unset($callbacks[$index]);
                $this->channelCallbacks[$channel] = array_values($callbacks);
            }
        }

        if (! isset($this->channelCallbacks[$channel]) || $this->channelCallbacks[$channel] === []) {
            unset($this->channelCallbacks[$channel]);

            if ($this->connection !== null && ! $this->connection->isClosed()) {
                $promise = $this->connection->enqueue(new UnsubscribeCommand([$channel]))
                    ->then(function (): void {
                    })
                ;

                return Promise::propagateCancellation($promise);
            }
        }

        return Promise::resolved();
    }

    /**
     * {@inheritDoc}
     */
    public function psubscribe(string $pattern, callable $callback): PromiseInterface
    {
        if ($this->isClosed) {
            return Promise::rejected(new ConnectionException('Cannot subscribe: Subscriber is closed.'));
        }

        $isFirst = ! isset($this->patternCallbacks[$pattern]) || $this->patternCallbacks[$pattern] === [];
        $this->patternCallbacks[$pattern][] = $callback;

        if (! $isFirst) {
            return Promise::resolved();
        }

        $innerPromise = null;

        $promise = $this->getConnection()->then(function (Connection $conn) use ($pattern, &$innerPromise): PromiseInterface {
            $innerPromise = $conn->enqueue(new PsubscribeCommand([$pattern]))->then(function (): void {
            });

            return $innerPromise;
        });

        Promise::forwardCancellation($promise, $innerPromise);

        $promise->onCancel(function () use ($pattern, $callback): void {
            $this->punsubscribe($pattern, $callback)->catch(static fn () => null);
        });

        return Promise::propagateCancellation($promise);
    }

    /**
     * {@inheritDoc}
     */
    public function punsubscribe(string $pattern, ?callable $callback = null): PromiseInterface
    {
        if ($this->isClosed) {
            return Promise::resolved();
        }

        if ($callback === null) {
            unset($this->patternCallbacks[$pattern]);
        } elseif (isset($this->patternCallbacks[$pattern])) {
            $callbacks = $this->patternCallbacks[$pattern];
            $index = array_search($callback, $callbacks, true);

            if ($index !== false) {
                unset($callbacks[$index]);
                $this->patternCallbacks[$pattern] = array_values($callbacks);
            }
        }

        if (! isset($this->patternCallbacks[$pattern]) || $this->patternCallbacks[$pattern] === []) {
            unset($this->patternCallbacks[$pattern]);

            if ($this->connection !== null && ! $this->connection->isClosed()) {
                $promise = $this->connection->enqueue(new PunsubscribeCommand([$pattern]))
                    ->then(function (): void {
                    })
                ;

                return Promise::propagateCancellation($promise);
            }
        }

        return Promise::resolved();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): PromiseInterface
    {
        if ($this->isClosed) {
            return Promise::resolved();
        }

        $this->isClosed = true;
        $this->channelCallbacks = [];
        $this->patternCallbacks = [];

        $conn = $this->connection;
        $this->connection = null;
        $this->connectionPromise = null;

        if ($conn !== null && ! $conn->isClosed()) {
            $conn->close();
        }

        return Promise::resolved();
    }

    /**
     * Retrieves the active connection or creates a new one if currently disconnected.
     *
     * @return PromiseInterface<Connection>
     */
    private function getConnection(): PromiseInterface
    {
        if ($this->connectionPromise !== null) {
            return $this->connectionPromise;
        }

        /** @var Promise<Connection> $promise */
        $promise = new Promise();
        $this->connectionPromise = $promise;

        Connection::create($this->config)->then(
            function (Connection $conn) use ($promise): void {
                if ($this->isClosed) {
                    $conn->close();
                    $promise->reject(new ConnectionException('Subscriber closed during connection'));

                    return;
                }

                $this->connection = $conn;
                $conn->setPubSubCallback($this->routeMessage(...));
                $conn->onClose($this->handleDisconnect(...));

                $promise->resolve($conn);
            },
            function (Throwable $e) use ($promise): void {
                $this->connectionPromise = null;
                $promise->reject($e);
            }
        );

        return $promise;
    }

    private function handleDisconnect(): void
    {
        if ($this->isClosed) {
            return;
        }

        $this->connection = null;
        $this->connectionPromise = null;

        $this->reconnectWithBackoff($this->minReconnectInterval);
    }

    private function reconnectWithBackoff(float $delay): void
    {
        if ($this->isClosed) {
            return;
        }

        Loop::addTimer($delay, function () use ($delay): void {
            if ($this->isClosed) {
                return;
            }

            $this->getConnection()
                ->then(function (Connection $conn): PromiseInterface {
                    $subs = [];
                    $channels = array_keys($this->channelCallbacks);
                    $patterns = array_keys($this->patternCallbacks);

                    if ($channels !== []) {
                        $subs[] = $conn->enqueue(new SubscribeCommand($channels));
                    }
                    if ($patterns !== []) {
                        $subs[] = $conn->enqueue(new PsubscribeCommand($patterns));
                    }

                    return $subs !== [] ? Promise::all($subs) : Promise::resolved();
                })
                ->catch(function () use ($delay): void {
                    $this->reconnectWithBackoff(min($delay * 2, $this->maxReconnectInterval));
                })
            ;
        });
    }

    /**
     * @param array<int, mixed> $message
     */
    private function routeMessage(array $message): void
    {
        $type = strtolower($this->safeString($message[0] ?? null));

        if ($type === 'message' && isset($message[1], $message[2])) {
            $channel = $this->safeString($message[1]);
            $payload = $this->safeString($message[2]);

            if (isset($this->channelCallbacks[$channel])) {
                foreach ($this->channelCallbacks[$channel] as $callback) {
                    $callback($channel, $payload);
                }
            }
        } elseif ($type === 'pmessage' && isset($message[1], $message[2], $message[3])) {
            $pattern = $this->safeString($message[1]);
            $channel = $this->safeString($message[2]);
            $payload = $this->safeString($message[3]);

            if (isset($this->patternCallbacks[$pattern])) {
                foreach ($this->patternCallbacks[$pattern] as $callback) {
                    $callback($pattern, $channel, $payload);
                }
            }
        }
    }

    private function safeString(mixed $value): string
    {
        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    public function __destruct()
    {
        $this->close();
    }
}
