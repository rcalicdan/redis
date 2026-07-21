<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis GET command.
 *
 * Retrieves the string value associated with the specified key. If the key
 * does not exist, it resolves to null. If the value stored at key is not a string,
 * an error is returned because GET only handles string values.
 *
 * @see https://redis.io/commands/get/
 * @extends AbstractCommand<string|null>
 */
final class GetCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'GET'; }
}