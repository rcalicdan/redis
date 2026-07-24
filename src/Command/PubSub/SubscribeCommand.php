<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis SUBSCRIBE command.
 *
 * Subscribes the client to the specified channels. Once a client enters the
 * subscribed state, it receives messages broadcast to those channels.
 *
 * Resolves to the server's subscription acknowledgement response array:
 * `["subscribe", channelName, activeSubscriptionsCount]`.
 *
 * @see https://redis.io/commands/subscribe/
 *
 * @extends AbstractCommand<array<int, mixed>>
 */
final class SubscribeCommand extends AbstractCommand
{
    public string $id {
        get => 'SUBSCRIBE';
    }
}
