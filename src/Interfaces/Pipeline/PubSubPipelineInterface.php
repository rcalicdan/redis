<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface PubSubPipelineInterface
{
    /**
     * Adds a PUBLISH command to the pipeline.
     *
     * @param string $channel The channel to broadcast to.
     * @param string $message The message payload.
     *
     * @return self For method chaining.
     */
    public function publish(string $channel, string $message): self;
}
