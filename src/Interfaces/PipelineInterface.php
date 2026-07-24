<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

/**
 * Contract for building a Redis pipeline.
 *
 * Pipelines allow multiple commands to be batched and sent to the Redis server
 * in a single TCP write operation, significantly improving throughput.
 */
interface PipelineInterface
{
    /**
     * Adds a PING command to the pipeline.
     *
     * @param string|null $message Optional message to echo back.
     *
     * @return self For method chaining.
     */
    public function ping(?string $message = null): self;

    /**
     * Adds a PUBLISH command to the pipeline.
     *
     * @param string $channel The channel to broadcast to.
     * @param string $message The message payload.
     *
     * @return self For method chaining.
     */
    public function publish(string $channel, string $message): self;

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

    /**
     * Adds a GET command to the pipeline.
     *
     * @param string $key The key to retrieve.
     *
     * @return self For method chaining.
     */
    public function get(string $key): self;

    /**
     * Adds a SET command to the pipeline.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to store.
     *
     * @return self For method chaining.
     */
    public function set(string $key, mixed $value): self;

    /**
     * Adds an MGET command to the pipeline.
     *
     * @param string ...$keys The keys to retrieve.
     *
     * @return self For method chaining.
     */
    public function mget(string ...$keys): self;

    /**
     * Adds an INCR command to the pipeline.
     *
     * @param string $key The key to increment.
     *
     * @return self For method chaining.
     */
    public function incr(string $key): self;

    /**
     * Adds a DECR command to the pipeline.
     *
     * @param string $key The key to decrement.
     *
     * @return self For method chaining.
     */
    public function decr(string $key): self;

    /**
     * Adds an INCRBY command to the pipeline.
     *
     * @param string $key The key to increment.
     * @param int $increment The integer amount to increment by.
     *
     * @return self For method chaining.
     */
    public function incrby(string $key, int $increment): self;

    /**
     * Adds an INCRBYFLOAT command to the pipeline.
     *
     * @param string $key The key to increment.
     * @param float $increment The float amount to increment by.
     *
     * @return self For method chaining.
     */
    public function incrbyfloat(string $key, float $increment): self;

    /**
     * Adds a SETEX command to the pipeline.
     *
     * @param string $key The key to set.
     * @param int $seconds Time to live in seconds.
     * @param mixed $value The value to store.
     *
     * @return self For method chaining.
     */
    public function setex(string $key, int $seconds, mixed $value): self;

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

    /**
     * Adds a SADD command to the pipeline.
     *
     * @param string $key The set key.
     * @param mixed ...$members Members to add.
     *
     * @return self For method chaining.
     */
    public function sadd(string $key, mixed ...$members): self;

    /**
     * Adds a SREM command to the pipeline.
     *
     * @param string $key The set key.
     * @param mixed ...$members Members to remove.
     *
     * @return self For method chaining.
     */
    public function srem(string $key, mixed ...$members): self;

    /**
     * Adds a SMEMBERS command to the pipeline.
     *
     * @param string $key The set key.
     *
     * @return self For method chaining.
     */
    public function smembers(string $key): self;

    /**
     * Adds a SISMEMBER command to the pipeline.
     *
     * @param string $key The set key.
     * @param mixed $member Member to test.
     *
     * @return self For method chaining.
     */
    public function sismember(string $key, mixed $member): self;

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
