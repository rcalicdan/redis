<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Redis\Command\Connection\PingCommand;
use Hibla\Redis\Command\Hashes\HdelCommand;
use Hibla\Redis\Command\Hashes\HexistsCommand;
use Hibla\Redis\Command\Hashes\HgetallCommand;
use Hibla\Redis\Command\Hashes\HgetCommand;
use Hibla\Redis\Command\Hashes\HmgetCommand;
use Hibla\Redis\Command\Hashes\HsetCommand;
use Hibla\Redis\Command\Keys\DelCommand;
use Hibla\Redis\Command\Keys\ExistsCommand;
use Hibla\Redis\Command\Keys\ExpireCommand;
use Hibla\Redis\Command\Keys\TtlCommand;
use Hibla\Redis\Command\Keys\TypeCommand;
use Hibla\Redis\Command\Keys\UnlinkCommand;
use Hibla\Redis\Command\Lists\BlpopCommand;
use Hibla\Redis\Command\Lists\LlenCommand;
use Hibla\Redis\Command\Lists\LpopCommand;
use Hibla\Redis\Command\Lists\LpushCommand;
use Hibla\Redis\Command\Lists\RpopCommand;
use Hibla\Redis\Command\Lists\RpushCommand;
use Hibla\Redis\Command\PubSub\PublishCommand;
use Hibla\Redis\Command\Sets\SaddCommand;
use Hibla\Redis\Command\Sets\SismemberCommand;
use Hibla\Redis\Command\Sets\SmembersCommand;
use Hibla\Redis\Command\Sets\SremCommand;
use Hibla\Redis\Command\SortedSets\ZaddCommand;
use Hibla\Redis\Command\SortedSets\ZrangeCommand;
use Hibla\Redis\Command\SortedSets\ZremCommand;
use Hibla\Redis\Command\SortedSets\ZscoreCommand;
use Hibla\Redis\Command\Strings\DecrCommand;
use Hibla\Redis\Command\Strings\GetCommand;
use Hibla\Redis\Command\Strings\IncrbyCommand;
use Hibla\Redis\Command\Strings\IncrbyfloatCommand;
use Hibla\Redis\Command\Strings\IncrCommand;
use Hibla\Redis\Command\Strings\MgetCommand;
use Hibla\Redis\Command\Strings\SetCommand;
use Hibla\Redis\Command\Strings\SetexCommand;
use Hibla\Redis\Interfaces\CommandInterface;
use Hibla\Redis\Interfaces\PipelineInterface;
use LogicException;

/**
 * @internal Created via RedisClient::pipeline() or RedisClient::atomic(). Do not instantiate directly.
 */
final class Pipeline implements PipelineInterface
{
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
    public function ping(?string $message = null): self
    {
        $this->checkLocked();
        $this->commands[] = new PingCommand($message === null ? [] : [$message]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(string $channel, string $message): self
    {
        $this->checkLocked();
        $this->commands[] = new PublishCommand([$channel, $message]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function del(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new DelCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new ExistsCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function expire(string $key, int $seconds): self
    {
        $this->checkLocked();
        $this->commands[] = new ExpireCommand([$key, $seconds]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function ttl(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new TtlCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function type(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new TypeCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function unlink(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new UnlinkCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new GetCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): self
    {
        $this->checkLocked();
        $this->commands[] = new SetCommand([$key, $value]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function mget(string ...$keys): self
    {
        $this->checkLocked();
        $this->commands[] = new MgetCommand($keys);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function incr(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new IncrCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function decr(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new DecrCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function incrby(string $key, int $increment): self
    {
        $this->checkLocked();
        $this->commands[] = new IncrbyCommand([$key, $increment]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function incrbyfloat(string $key, float $increment): self
    {
        $this->checkLocked();
        $this->commands[] = new IncrbyfloatCommand([$key, $increment]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setex(string $key, int $seconds, mixed $value): self
    {
        $this->checkLocked();
        $this->commands[] = new SetexCommand([$key, $seconds, $value]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hget(string $key, string $field): self
    {
        $this->checkLocked();
        $this->commands[] = new HgetCommand([$key, $field]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hset(string $key, string ...$fieldsAndValues): self
    {
        $this->checkLocked();
        $this->commands[] = new HsetCommand([$key, ...$fieldsAndValues]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hgetall(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new HgetallCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hdel(string $key, string ...$fields): self
    {
        $this->checkLocked();
        $this->commands[] = new HdelCommand([$key, ...$fields]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hexists(string $key, string $field): self
    {
        $this->checkLocked();
        $this->commands[] = new HexistsCommand([$key, $field]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hmget(string $key, string ...$fields): self
    {
        $this->checkLocked();
        $this->commands[] = new HmgetCommand([$key, ...$fields]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function lpush(string $key, mixed ...$values): self
    {
        $this->checkLocked();
        $this->commands[] = new LpushCommand([$key, ...$values]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function rpush(string $key, mixed ...$values): self
    {
        $this->checkLocked();
        $this->commands[] = new RpushCommand([$key, ...$values]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function lpop(string $key, ?int $count = null): self
    {
        $this->checkLocked();
        $args = $count !== null ? [$key, $count] : [$key];
        $this->commands[] = new LpopCommand($args);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function rpop(string $key, ?int $count = null): self
    {
        $this->checkLocked();
        $args = $count !== null ? [$key, $count] : [$key];
        $this->commands[] = new RpopCommand($args);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function llen(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new LlenCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function blpop(string|array $keys, float|int $timeout = 0): self
    {
        $this->checkLocked();
        $args = \is_array($keys) ? $keys : [$keys];
        $args[] = $timeout;
        $this->commands[] = new BlpopCommand($args);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function sadd(string $key, mixed ...$members): self
    {
        $this->checkLocked();
        $this->commands[] = new SaddCommand([$key, ...$members]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function srem(string $key, mixed ...$members): self
    {
        $this->checkLocked();
        $this->commands[] = new SremCommand([$key, ...$members]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function smembers(string $key): self
    {
        $this->checkLocked();
        $this->commands[] = new SmembersCommand([$key]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function sismember(string $key, mixed $member): self
    {
        $this->checkLocked();
        $this->commands[] = new SismemberCommand([$key, $member]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function zadd(string $key, float|int $score, string $member, mixed ...$additionalScoresAndMembers): self
    {
        $this->checkLocked();
        $this->commands[] = new ZaddCommand([$key, $score, $member, ...$additionalScoresAndMembers]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function zrem(string $key, string ...$members): self
    {
        $this->checkLocked();
        $this->commands[] = new ZremCommand([$key, ...$members]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function zrange(string $key, int|string $start, int|string $stop): self
    {
        $this->checkLocked();
        $this->commands[] = new ZrangeCommand([$key, $start, $stop]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function zscore(string $key, string $member): self
    {
        $this->checkLocked();
        $this->commands[] = new ZscoreCommand([$key, $member]);

        return $this;
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
