<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis UNSUBSCRIBE command.
 *
 * Unsubscribes the client from the given channels, or from all subscribed
 * channels if no channels are specified.
 *
 * Resolves to the server's unsubscription acknowledgement response array:
 * `["unsubscribe", channelName, remainingSubscriptionsCount]`.
 *
 * @see https://redis.io/commands/unsubscribe/
 *
 * @extends AbstractCommand<array<int, mixed>>
 */
final class UnsubscribeCommand extends AbstractCommand
{
    public string $id {
        get => 'UNSUBSCRIBE';
    }
}
