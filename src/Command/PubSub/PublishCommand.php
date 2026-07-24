<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis PUBLISH command.
 *
 * Posts a message payload to the specified channel.
 * Resolves to the integer number of subscribed clients that received the message.
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
