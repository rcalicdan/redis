<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Hashes\HdelCommand;
use Hibla\Redis\Command\Hashes\HexistsCommand;
use Hibla\Redis\Command\Hashes\HgetallCommand;
use Hibla\Redis\Command\Hashes\HgetCommand;
use Hibla\Redis\Command\Hashes\HmgetCommand;
use Hibla\Redis\Command\Hashes\HsetCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait HashesCommandsTrait
{
    /**
     * @template TReturn
     * @param CommandInterface<TReturn> $command
     * @return PromiseInterface<TReturn>
     */
    abstract public function executeCommand(CommandInterface $command): PromiseInterface;

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string|null>
     */
    public function hget(string $key, string $field): PromiseInterface
    {
        return $this->executeCommand(new HgetCommand([$key, $field]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function hset(string $key, string ...$fieldsAndValues): PromiseInterface
    {
        return $this->executeCommand(new HsetCommand([$key, ...$fieldsAndValues]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<array<string, string>>
     */
    public function hgetall(string $key): PromiseInterface
    {
        return $this->executeCommand(new HgetallCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function hdel(string $key, string ...$fields): PromiseInterface
    {
        return $this->executeCommand(new HdelCommand([$key, ...$fields]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function hexists(string $key, string $field): PromiseInterface
    {
        return $this->executeCommand(new HexistsCommand([$key, $field]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<array<int, string|null>>
     */
    public function hmget(string $key, string ...$fields): PromiseInterface
    {
        return $this->executeCommand(new HmgetCommand([$key, ...$fields]));
    }
}