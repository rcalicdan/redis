<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Redis\Interfaces\Pipeline\ConnectionPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\HashesPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\KeysPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\ListsPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\PubSubPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\SetsPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\SortedSetsPipelineInterface;
use Hibla\Redis\Interfaces\Pipeline\StringsPipelineInterface;

/**
 * Contract for building a Redis pipeline.
 *
 * Pipelines allow multiple commands to be batched and sent to the Redis server
 * in a single TCP write operation, significantly improving throughput.
 */
interface PipelineInterface extends
    ConnectionPipelineInterface,
    HashesPipelineInterface,
    KeysPipelineInterface,
    ListsPipelineInterface,
    PubSubPipelineInterface,
    SetsPipelineInterface,
    SortedSetsPipelineInterface,
    StringsPipelineInterface
{
    /**
     * Adds a custom or raw CommandInterface to the pipeline.
     *
     * @template TResponse
     *
     * @param CommandInterface<TResponse> $command The command to execute.
     *
     * @return self For method chaining.
     */
    public function executeCommand(CommandInterface $command): self;
}
