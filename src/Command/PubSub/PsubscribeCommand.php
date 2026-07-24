<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis PSUBSCRIBE command.
 *
 * Subscribes the client to the given glob-style patterns (e.g. `news.*` or `user:*`).
 * Any message published to a channel matching any of the specified patterns will
 * be delivered to the client.
 *
 * Resolves to the server's pattern subscription acknowledgement response array:
 * `["psubscribe", pattern, activePatternSubscriptionsCount]`.
 *
 * @see https://redis.io/commands/psubscribe/
 *
 * @extends AbstractCommand<array<int, mixed>>
 */
final class PsubscribeCommand extends AbstractCommand
{
    public string $id {
        get => 'PSUBSCRIBE';
    }

    public function isBlocking(): bool
    {
        return true;
    }
}
