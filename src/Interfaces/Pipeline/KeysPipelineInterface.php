<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface KeysPipelineInterface
{
    /**
     * Adds a DEL command to the pipeline.
     *
     * @param string ...$keys One or more keys to delete.
     *
     * @return self For method chaining.
     */
    public function del(string ...$keys): self;

    /**
     * Adds an EXISTS command to the pipeline.
     *
     * @param string ...$keys One or more keys to check.
     *
     * @return self For method chaining.
     */
    public function exists(string ...$keys): self;

    /**
     * Adds an EXPIRE command to the pipeline.
     *
     * @param string $key The target key.
     * @param int $seconds Timeout in seconds.
     *
     * @return self For method chaining.
     */
    public function expire(string $key, int $seconds): self;

    /**
     * Adds a TTL command to the pipeline.
     *
     * @param string $key The key to inspect.
     *
     * @return self For method chaining.
     */
    public function ttl(string $key): self;

    /**
     * Adds a TYPE command to the pipeline.
     *
     * @param string $key The key to inspect.
     *
     * @return self For method chaining.
     */
    public function type(string $key): self;

    /**
     * Adds an UNLINK command to the pipeline.
     *
     * @param string ...$keys One or more keys to unlink.
     *
     * @return self For method chaining.
     */
    public function unlink(string ...$keys): self;
}
