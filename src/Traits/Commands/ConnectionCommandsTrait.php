<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait ConnectionCommandsTrait
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
     * @return PromiseInterface<string>
     */
    public function ping(?string $message = null): PromiseInterface
    {
        return $this->executeCommand(new PingCommand($message === null ? [] : [$message]));
    }
}