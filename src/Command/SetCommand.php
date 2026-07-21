<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis SET command.
 *
 * Sets key to hold the string value. If key already holds a value, it is overwritten,
 * regardless of its type. Any previous time to live associated with the key is discarded
 * on a successful SET operation.
 *
 * @see https://redis.io/commands/set/
 * @extends AbstractCommand<string>
 */
final class SetCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'SET'; }
}