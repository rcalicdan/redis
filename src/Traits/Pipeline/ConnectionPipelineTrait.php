<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait ConnectionPipelineTrait
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
    public function ping(?string $message = null): self
    {
        return $this->executeCommand(new PingCommand($message === null ? [] : [$message]));
    }
}
