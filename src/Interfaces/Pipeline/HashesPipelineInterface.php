<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface HashesPipelineInterface
{
    /**
     * Adds an HGET command to the pipeline.
     *
     * @param string $key The hash key.
     * @param string $field The field name.
     *
     * @return self For method chaining.
     */
    public function hget(string $key, string $field): self;

    /**
     * Adds an HSET command to the pipeline.
     *
     * @param string $key The hash key.
     * @param string ...$fieldsAndValues Variadic field name and value pairs.
     *
     * @return self For method chaining.
     */
    public function hset(string $key, string ...$fieldsAndValues): self;

    /**
     * Adds an HGETALL command to the pipeline.
     *
     * @param string $key The hash key.
     *
     * @return self For method chaining.
     */
    public function hgetall(string $key): self;

    /**
     * Adds an HDEL command to the pipeline.
     *
     * @param string $key The hash key.
     * @param string ...$fields One or more fields to delete.
     *
     * @return self For method chaining.
     */
    public function hdel(string $key, string ...$fields): self;

    /**
     * Adds an HEXISTS command to the pipeline.
     *
     * @param string $key The hash key.
     * @param string $field The field name to check.
     *
     * @return self For method chaining.
     */
    public function hexists(string $key, string $field): self;

    /**
     * Adds an HMGET command to the pipeline.
     *
     * @param string $key The hash key.
     * @param string ...$fields The fields to retrieve.
     *
     * @return self For method chaining.
     */
    public function hmget(string $key, string ...$fields): self;
}
