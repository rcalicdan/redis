<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface ListsPipelineInterface
{
    /**
     * Adds an LPUSH command to the pipeline.
     *
     * @param string $key The list key.
     * @param mixed ...$values Values to prepend.
     *
     * @return self For method chaining.
     */
    public function lpush(string $key, mixed ...$values): self;

    /**
     * Adds an RPUSH command to the pipeline.
     *
     * @param string $key The list key.
     * @param mixed ...$values Values to append.
     *
     * @return self For method chaining.
     */
    public function rpush(string $key, mixed ...$values): self;

    /**
     * Adds an LPOP command to the pipeline.
     *
     * @param string $key The list key.
     * @param int|null $count Optional number of elements to pop.
     *
     * @return self For method chaining.
     */
    public function lpop(string $key, ?int $count = null): self;

    /**
     * Adds an RPOP command to the pipeline.
     *
     * @param string $key The list key.
     * @param int|null $count Optional number of elements to pop.
     *
     * @return self For method chaining.
     */
    public function rpop(string $key, ?int $count = null): self;

    /**
     * Adds an LLEN command to the pipeline.
     *
     * @param string $key The list key.
     *
     * @return self For method chaining.
     */
    public function llen(string $key): self;

    /**
     * Adds a BLPOP command to the pipeline.
     *
     * @param string|array<string> $keys The list key(s) to pop from.
     * @param float|int $timeout Maximum time to block in seconds (0 = block indefinitely).
     *
     * @return self For method chaining.
     */
    public function blpop(string|array $keys, float|int $timeout = 0): self;
}
