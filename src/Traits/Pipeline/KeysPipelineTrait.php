<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\Keys\DelCommand;
use Hibla\Redis\Command\Keys\ExistsCommand;
use Hibla\Redis\Command\Keys\ExpireCommand;
use Hibla\Redis\Command\Keys\TtlCommand;
use Hibla\Redis\Command\Keys\TypeCommand;
use Hibla\Redis\Command\Keys\UnlinkCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait KeysPipelineTrait
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
    public function del(string ...$keys): self
    {
        return $this->executeCommand(new DelCommand($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string ...$keys): self
    {
        return $this->executeCommand(new ExistsCommand($keys));
    }

    /**
     * {@inheritDoc}
     */
    public function expire(string $key, int $seconds): self
    {
        return $this->executeCommand(new ExpireCommand([$key, $seconds]));
    }

    /**
     * {@inheritDoc}
     */
    public function ttl(string $key): self
    {
        return $this->executeCommand(new TtlCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function type(string $key): self
    {
        return $this->executeCommand(new TypeCommand([$key]));
    }

    /**
     * {@inheritDoc}
     */
    public function unlink(string ...$keys): self
    {
        return $this->executeCommand(new UnlinkCommand($keys));
    }
}
