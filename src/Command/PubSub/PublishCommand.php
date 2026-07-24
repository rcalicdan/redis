<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis PUBLISH command.
 *
 * Posts a message to the given channel.
 * Resolves to the integer number of clients that received the message.
 *
 * @see https://redis.io/commands/publish/
 *
 * @extends AbstractCommand<int>
 */
final class PublishCommand extends AbstractCommand
{
    public string $id {
        get => 'PUBLISH';
    }
}
