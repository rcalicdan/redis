<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Redis\Command\SortedSets\ZaddCommand;
use Hibla\Redis\Command\SortedSets\ZrangeCommand;
use Hibla\Redis\Command\SortedSets\ZremCommand;
use Hibla\Redis\Command\SortedSets\ZscoreCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait SortedSetsCommandsTrait
{
    /**
     * @template TReturn
     *
     * @param CommandInterface<TReturn> $command
     *
     * @return PromiseInterface<TReturn>
     */
    abstract public function executeCommand(CommandInterface $command): PromiseInterface;

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function zadd(string $key, float|int $score, string $member, mixed ...$additionalScoresAndMembers): PromiseInterface
    {
        return $this->executeCommand(new ZaddCommand([$key, $score, $member, ...$additionalScoresAndMembers]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<int>
     */
    public function zrem(string $key, string ...$members): PromiseInterface
    {
        return $this->executeCommand(new ZremCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<array<int, string>>
     */
    public function zrange(string $key, int|string $start, int|string $stop): PromiseInterface
    {
        return $this->executeCommand(new ZrangeCommand([$key, $start, $stop]));
    }

    /**
     * {@inheritDoc}
     *
     * @return PromiseInterface<string|null>
     */
    public function zscore(string $key, string $member): PromiseInterface
    {
        return $this->executeCommand(new ZscoreCommand([$key, $member]));
    }
}
