<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis PUNSUBSCRIBE command.
 *
 * Unsubscribes the client from the given glob-style patterns, or from all subscribed
 * patterns if no patterns are specified.
 *
 * Resolves to the server's pattern unsubscription acknowledgement response array:
 * `["punsubscribe", pattern, remainingPatternSubscriptionsCount]`.
 *
 * @see https://redis.io/commands/punsubscribe/
 *
 * @extends AbstractCommand<array<int, mixed>>
 */
final class PunsubscribeCommand extends AbstractCommand
{
    public string $id {
        get => 'PUNSUBSCRIBE';
    }
}
