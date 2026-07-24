<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Strings\DecrCommand;
use Hibla\Redis\Command\Strings\GetCommand;
use Hibla\Redis\Command\Strings\IncrbyCommand;
use Hibla\Redis\Command\Strings\IncrbyfloatCommand;
use Hibla\Redis\Command\Strings\IncrCommand;
use Hibla\Redis\Command\Strings\MgetCommand;
use Hibla\Redis\Command\Strings\SetCommand;
use Hibla\Redis\Command\Strings\SetexCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait StringsCommandsTrait
{
    /**
     * @template TReturn
     *
     * @param CommandInterface<TReturn> $command
     *
     * @return PromiseInterface<TReturn>
     */
    abstract public function executeCommand(CommandInterface $command): PromiseInterface;

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string|null>
     */
    public function get(string $key): PromiseInterface
    {
        return $this->executeCommand(new GetCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string>
     */
    public function set(string $key, mixed $value): PromiseInterface
    {
        return $this->executeCommand(new SetCommand([$key, $value]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<array<int, string|null>>
     */
    public function mget(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new MgetCommand($keys));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function incr(string $key): PromiseInterface
    {
        return $this->executeCommand(new IncrCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function decr(string $key): PromiseInterface
    {
        return $this->executeCommand(new DecrCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function incrby(string $key, int $increment): PromiseInterface
    {
        return $this->executeCommand(new IncrbyCommand([$key, $increment]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string>
     */
    public function incrbyfloat(string $key, float $increment): PromiseInterface
    {
        return $this->executeCommand(new IncrbyfloatCommand([$key, $increment]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string>
     */
    public function setex(string $key, int $seconds, mixed $value): PromiseInterface
    {
        return $this->executeCommand(new SetexCommand([$key, $seconds, $value]));
    }
}
