<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Keys\DelCommand;
use Hibla\Redis\Command\Keys\ExistsCommand;
use Hibla\Redis\Command\Keys\ExpireCommand;
use Hibla\Redis\Command\Keys\TtlCommand;
use Hibla\Redis\Command\Keys\TypeCommand;
use Hibla\Redis\Command\Keys\UnlinkCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait KeysCommandsTrait
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
    public function del(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new DelCommand($keys));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function exists(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new ExistsCommand($keys));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function expire(string $key, int $seconds): PromiseInterface
    {
        return $this->executeCommand(new ExpireCommand([$key, $seconds]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function ttl(string $key): PromiseInterface
    {
        return $this->executeCommand(new TtlCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string>
     */
    public function type(string $key): PromiseInterface
    {
        return $this->executeCommand(new TypeCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function unlink(string ...$keys): PromiseInterface
    {
        return $this->executeCommand(new UnlinkCommand($keys));
    }
}