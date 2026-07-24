<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Connection;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis AUTH command.
 *
 * Authenticates the connection using either a password, or a username and password
 * (supported in Redis 6.0+ via Access Control Lists / ACL). This is executed
 * internally during the connection setup if credentials are provided in the config.
 *
 * @see https://redis.io/commands/auth/
 *
 * @extends AbstractCommand<string>
 */
final class AuthCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'AUTH'; }
}
