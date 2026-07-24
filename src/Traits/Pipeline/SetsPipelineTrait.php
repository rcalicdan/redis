<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Sets\SaddCommand;
use Hibla\Redis\Command\Sets\SismemberCommand;
use Hibla\Redis\Command\Sets\SmembersCommand;
use Hibla\Redis\Command\Sets\SremCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait SetsPipelineTrait
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
    public function sadd(string $key, mixed ...$members): self
    {
        return $this->executeCommand(new SaddCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     */
    public function srem(string $key, mixed ...$members): self
    {
        return $this->executeCommand(new SremCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     */
    public function smembers(string $key): self
    {
        return $this->executeCommand(new SmembersCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function sismember(string $key, mixed $member): self
    {
        return $this->executeCommand(new SismemberCommand([$key, $member]));
    }
}
