<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Sets\SaddCommand;
use Hibla\Redis\Command\Sets\SismemberCommand;
use Hibla\Redis\Command\Sets\SmembersCommand;
use Hibla\Redis\Command\Sets\SremCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait SetsCommandsTrait
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
     * @return PromiseInterface<int>
     */
    public function sadd(string $key, mixed ...$members): PromiseInterface
    {
        return $this->executeCommand(new SaddCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function srem(string $key, mixed ...$members): PromiseInterface
    {
        return $this->executeCommand(new SremCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<array<int, string>>
     */
    public function smembers(string $key): PromiseInterface
    {
        return $this->executeCommand(new SmembersCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function sismember(string $key, mixed $member): PromiseInterface
    {
        return $this->executeCommand(new SismemberCommand([$key, $member]));
    }
}
