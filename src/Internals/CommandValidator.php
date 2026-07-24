<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Interfaces\CommandInterface;

/**
 * @internal
 */
final class CommandValidator
{
    private const array FORBIDDEN_POOL_COMMANDS = [
        'SUBSCRIBE' => true,
        'PSUBSCRIBE' => true,
        'UNSUBSCRIBE' => true,
        'PUNSUBSCRIBE' => true,
    ];

    /**
     * @template TResponse
     *
     * @param CommandInterface<TResponse> $command
     */
    public static function checkValidForPool(CommandInterface $command): ?RedisException
    {
        $id = strtoupper($command->id);

        if (isset(self::FORBIDDEN_POOL_COMMANDS[$id])) {
            return new RedisException("Pub/Sub commands ({$command->id}) cannot be executed on the general connection pool. Please use createSubscriber() instead.");
        }

        return null;
    }

    /**
     * @param array<int, CommandInterface<mixed>> $commands
     */
    public static function checkBatchValidForPool(array $commands): ?RedisException
    {
        foreach ($commands as $command) {
            if (($error = self::checkValidForPool($command)) !== null) {
                return $error;
            }
        }

        return null;
    }
}
