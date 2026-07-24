<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Hashes\HdelCommand;
use Hibla\Redis\Command\Hashes\HexistsCommand;
use Hibla\Redis\Command\Hashes\HgetallCommand;
use Hibla\Redis\Command\Hashes\HgetCommand;
use Hibla\Redis\Command\Hashes\HmgetCommand;
use Hibla\Redis\Command\Hashes\HsetCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait HashesPipelineTrait
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
    public function hget(string $key, string $field): self
    {
        return $this->executeCommand(new HgetCommand([$key, $field]));
    }

    /**
     * {@inheritDoc}
     */
    public function hset(string $key, string ...$fieldsAndValues): self
    {
        return $this->executeCommand(new HsetCommand([$key, ...$fieldsAndValues]));
    }

    /**
     * {@inheritDoc}
     */
    public function hgetall(string $key): self
    {
        return $this->executeCommand(new HgetallCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function hdel(string $key, string ...$fields): self
    {
        return $this->executeCommand(new HdelCommand([$key, ...$fields]));
    }

    /**
     * {@inheritDoc}
     */
    public function hexists(string $key, string $field): self
    {
        return $this->executeCommand(new HexistsCommand([$key, $field]));
    }

    /**
     * {@inheritDoc}
     */
    public function hmget(string $key, string ...$fields): self
    {
        return $this->executeCommand(new HmgetCommand([$key, ...$fields]));
    }
}
