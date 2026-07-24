<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface ConnectionPipelineInterface
{
    /**
     * Adds a PING command to the pipeline.
     *
     * @param string|null $message Optional message to echo back.
     *
     * @return self For method chaining.
     */
    public function ping(?string $message = null): self;
}
