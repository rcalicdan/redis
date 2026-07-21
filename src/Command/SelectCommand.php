<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis SELECT command.
 *
 * Selects the logical database having the specified zero-based numeric index.
 * Newly created connections automatically select database 0. This is managed
 * internally during connection setup if a specific database index is configured.
 *
 * @see https://redis.io/commands/select/
 *
 * @extends AbstractCommand<string>
 */
final class SelectCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'SELECT'; }
}
