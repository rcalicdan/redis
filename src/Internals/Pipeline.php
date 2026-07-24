<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Command\Hashes\HgetallCommand;
use Hibla\Redis\Command\Keys\DelCommand;
use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Command\PubSub\PublishCommand;
use Hibla\Redis\Command\Strings\GetCommand;
use Hibla\Redis\Command\Strings\MgetCommand;
use Hibla\Redis\Command\Strings\SetCommand;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Interfaces\PipelineInterface;
use LogicException;

/**
 * @internal Created via RedisClient::pipeline(). Do not instantiate directly.
 */
final class Pipeline implements PipelineInterface
{
    /**
     * @internal
     *
     * @var array<int, CommandInterface<mixed>>
     */
    public private(set) array $commands = [];

    private bool $locked = false;

    /**
     * @internal
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    private function checkLocked(): void
    {
        if ($this->locked) {
            throw new LogicException('Cannot add commands to a pipeline that has already been executed.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function ping(?string $message = null): self
    {
        $this->checkLocked();
        $this->commands[] = new PingCommand($message === null ? [] : [$message]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new GetCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): self
    {
        $this->checkLocked();
        $this->commands[] = new SetCommand([$key, $value]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function del(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new DelCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function mget(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new MgetCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hgetall(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new HgetallCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function blpop(string|array $keys, float|int $timeout = 0): self
    {
        $this->checkLocked();
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;
        $this->commands[] = new BlpopCommand($args);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(string $channel, string $message): self
    {
        $this->checkLocked();
        $this->commands[] = new PublishCommand([$channel, $message]);

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @template TResponse
     *
     * @param CommandInterface<TResponse> $command
     */
    public function executeCommand(CommandInterface $command): self
    {
        $this->checkLocked();
        $this->commands[] = $command;

        return $this;
    }
}
