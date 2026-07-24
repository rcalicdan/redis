<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Pipeline;

use Hibla\Redis\Command\SortedSets\ZaddCommand;
use Hibla\Redis\Command\SortedSets\ZrangeCommand;
use Hibla\Redis\Command\SortedSets\ZremCommand;
use Hibla\Redis\Command\SortedSets\ZscoreCommand;
use Hibla\Redis\Interfaces\CommandInterface;

trait SortedSetsPipelineTrait
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
    public function zadd(string $key, float|int $score, string $member, mixed ...$additionalScoresAndMembers): self
    {
        return $this->executeCommand(new ZaddCommand([$key, $score, $member, ...$additionalScoresAndMembers]));
    }

    /**
     * {@inheritDoc}
     */
    public function zrem(string $key, string ...$members): self
    {
        return $this->executeCommand(new ZremCommand([$key, ...$members]));
    }

    /**
     * {@inheritDoc}
     */
    public function zrange(string $key, int|string $start, int|string $stop): self
    {
        return $this->executeCommand(new ZrangeCommand([$key, $start, $stop]));
    }

    /**
     * {@inheritDoc}
     */
    public function zscore(string $key, string $member): self
    {
        return $this->executeCommand(new ZscoreCommand([$key, $member]));
    }
}
