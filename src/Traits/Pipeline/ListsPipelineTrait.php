<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Command\Lists\LlenCommand;
use Hibla\Redis\Command\Lists\LpopCommand;
use Hibla\Redis\Command\Lists\LpushCommand;
use Hibla\Redis\Command\Lists\RpopCommand;
use Hibla\Redis\Command\Lists\RpushCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait ListsPipelineTrait
{
    /**
     * @template TResponse
     *
     * @param CommandInterface<TResponse> $command
     *
     * @return self
     */
    abstract public function executeCommand(CommandInterface $command): self;

    /**
     * {@inheritDoc}
     */
    public function lpush(string $key, mixed ...$values): self
    {
        return $this->executeCommand(new LpushCommand([$key, ...$values]));
    }

    /**
     * {@inheritDoc}
     */
    public function rpush(string $key, mixed ...$values): self
    {
        return $this->executeCommand(new RpushCommand([$key, ...$values]));
    }

    /**
     * {@inheritDoc}
     */
    public function lpop(string $key, ?int $count = null): self
    {
        $args = $count !== null ? [$key, $count] : [$key];

        return $this->executeCommand(new LpopCommand($args));
    }

    /**
     * {@inheritDoc}
     */
    public function rpop(string $key, ?int $count = null): self
    {
        $args = $count !== null ? [$key, $count] : [$key];

        return $this->executeCommand(new RpopCommand($args));
    }

    /**
     * {@inheritDoc}
     */
    public function llen(string $key): self
    {
        return $this->executeCommand(new LlenCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function blpop(string|array $keys, float|int $timeout = 0): self
    {
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;

        return $this->executeCommand(new BlpopCommand($args));
    }
}
