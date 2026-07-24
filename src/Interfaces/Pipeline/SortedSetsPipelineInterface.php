<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Pipeline;

interface SortedSetsPipelineInterface
{
    /**
     * Adds a ZADD command to the pipeline.
     *
     * @param string $key The sorted set key.
     * @param float|int $score Score for the first member.
     * @param string $member Member name.
     * @param mixed ...$additionalScoresAndMembers Additional score/member pairs.
     *
     * @return self For method chaining.
     */
    public function zadd(string $key, float|int $score, string $member, mixed ...$additionalScoresAndMembers): self;

    /**
     * Adds a ZREM command to the pipeline.
     *
     * @param string $key The sorted set key.
     * @param string ...$members Members to remove.
     *
     * @return self For method chaining.
     */
    public function zrem(string $key, string ...$members): self;

    /**
     * Adds a ZRANGE command to the pipeline.
     *
     * @param string $key The sorted set key.
     * @param int|string $start Start range.
     * @param int|string $stop Stop range.
     *
     * @return self For method chaining.
     */
    public function zrange(string $key, int|string $start, int|string $stop): self;

    /**
     * Adds a ZSCORE command to the pipeline.
     *
     * @param string $key The sorted set key.
     * @param string $member Member name.
     *
     * @return self For method chaining.
     */
    public function zscore(string $key, string $member): self;
}
