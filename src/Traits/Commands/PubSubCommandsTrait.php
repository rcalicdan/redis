<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\PubSub\PublishCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait PubSubCommandsTrait
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
    public function publish(string $channel, string $message): PromiseInterface
    {
        return $this->executeCommand(new PublishCommand([$channel, $message]));
    }
}