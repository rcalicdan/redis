<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Strings\DecrCommand;
use Hibla\Redis\Command\Strings\GetCommand;
use Hibla\Redis\Command\Strings\IncrbyCommand;
use Hibla\Redis\Command\Strings\IncrbyfloatCommand;
use Hibla\Redis\Command\Strings\IncrCommand;
use Hibla\Redis\Command\Strings\MgetCommand;
use Hibla\Redis\Command\Strings\SetCommand;
use Hibla\Redis\Command\Strings\SetexCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait StringsPipelineTrait
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
    public function get(string $key): self
    {
        return $this->executeCommand(new GetCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): self
    {
        return $this->executeCommand(new SetCommand([$key, $value]));
    }

    /**
     * {@inheritDoc}
     */
    public function mget(string ...$keys): self
    {
        return $this->executeCommand(new MgetCommand($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function incr(string $key): self
    {
        return $this->executeCommand(new IncrCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function decr(string $key): self
    {
        return $this->executeCommand(new DecrCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function incrby(string $key, int $increment): self
    {
        return $this->executeCommand(new IncrbyCommand([$key, $increment]));
    }

    /**
     * {@inheritDoc}
     */
    public function incrbyfloat(string $key, float $increment): self
    {
        return $this->executeCommand(new IncrbyfloatCommand([$key, $increment]));
    }

    /**
     * {@inheritDoc}
     */
    public function setex(string $key, int $seconds, mixed $value): self
    {
        return $this->executeCommand(new SetexCommand([$key, $seconds, $value]));
    }
}
