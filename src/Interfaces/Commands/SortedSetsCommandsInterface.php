<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface SortedSetsCommandsInterface
{
    /**
     * Adds members with scores to sorted set stored at key.
     *
     * @param string $key Sorted set key.
     * @param float|int $score Score.
     * @param string $member Member name.
     * @param mixed ...$additionalScoresAndMembers Additional score/member pairs.
     *
     * @return PromiseInterface<int> Number of elements added.
     */
    public function zadd(string $key, float|int $score, string $member, mixed ...$additionalScoresAndMembers): PromiseInterface;

    /**
     * Removes members from sorted set stored at key.
     *
     * @param string $key Sorted set key.
     * @param string ...$members Members to remove.
     *
     * @return PromiseInterface<int> Number of elements removed.
     */
    public function zrem(string $key, string ...$members): PromiseInterface;

    /**
     * Returns range of elements in sorted set stored at key.
     *
     * @param string $key Sorted set key.
     * @param int|string $start Start range.
     * @param int|string $stop Stop range.
     *
     * @return PromiseInterface<array<int, string>> Elements in range.
     */
    public function zrange(string $key, int|string $start, int|string $stop): PromiseInterface;

    /**
     * Returns score of member in sorted set stored at key.
     *
     * @param string $key Sorted set key.
     * @param string $member Member name.
     *
     * @return PromiseInterface<string|null> Score string or null if missing.
     */
    public function zscore(string $key, string $member): PromiseInterface;
}
