<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Interfaces\PipelineInterface;
use Hibla\Redis\Traits\Pipeline\ConnectionPipelineTrait;
use Hibla\Redis\Traits\Pipeline\HashesPipelineTrait;
use Hibla\Redis\Traits\Pipeline\KeysPipelineTrait;
use Hibla\Redis\Traits\Pipeline\ListsPipelineTrait;
use Hibla\Redis\Traits\Pipeline\PubSubPipelineTrait;
use Hibla\Redis\Traits\Pipeline\SetsPipelineTrait;
use Hibla\Redis\Traits\Pipeline\SortedSetsPipelineTrait;
use Hibla\Redis\Traits\Pipeline\StringsPipelineTrait;
use LogicException;

/**
 * @internal Created via RedisClient::pipeline() or RedisClient::atomic(). Do not instantiate directly.
 */
final class Pipeline implements PipelineInterface
{
    use ConnectionPipelineTrait;
    use HashesPipelineTrait;
    use KeysPipelineTrait;
    use ListsPipelineTrait;
    use PubSubPipelineTrait;
    use SetsPipelineTrait;
    use SortedSetsPipelineTrait;
    use StringsPipelineTrait;

    /**
     * @internal
     *
     * @var array<int, CommandInterface<mixed>>
     */
    public private(set) array $commands = [];

    private bool $locked = false;

    /**
     * @internal
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    private function checkLocked(): void
    {
        if ($this->locked) {
            throw new LogicException('Cannot add commands to a pipeline that has already been executed.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function executeCommand(CommandInterface $command): self
    {
        $this->checkLocked();
        $this->commands[] = $command;

        return $this;
    }
}
