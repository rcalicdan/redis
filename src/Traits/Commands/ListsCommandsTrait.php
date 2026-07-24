<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Command\Lists\LlenCommand;
use Hibla\Redis\Command\Lists\LpopCommand;
use Hibla\Redis\Command\Lists\LpushCommand;
use Hibla\Redis\Command\Lists\RpopCommand;
use Hibla\Redis\Command\Lists\RpushCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait ListsCommandsTrait
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
    public function lpush(string $key, mixed ...$values): PromiseInterface
    {
        return $this->executeCommand(new LpushCommand([$key, ...$values]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function rpush(string $key, mixed ...$values): PromiseInterface
    {
        return $this->executeCommand(new RpushCommand([$key, ...$values]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string|array<int, string>|null>
     */
    public function lpop(string $key, int $count = 1): PromiseInterface
    {
        return $this->executeCommand(new LpopCommand([$key, $count]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string|array<int, string>|null>
     */
    public function rpop(string $key, int $count = 1): PromiseInterface
    {
        return $this->executeCommand(new RpopCommand([$key, $count]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function llen(string $key): PromiseInterface
    {
        return $this->executeCommand(new LlenCommand([$key]));
    }

    /**
     * {@inheritDoc}
     *
     * @param string|array<string> $keys
     * @return PromiseInterface<array<int, string>|null>
     */
    public function blpop(string|array $keys, float|int $timeout = 0): PromiseInterface
    {
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;

        return $this->executeCommand(new BlpopCommand($args));
    }
}