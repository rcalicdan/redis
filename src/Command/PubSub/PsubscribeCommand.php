<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\PubSub;

use Hibla\Redis\Command\AbstractCommand;

/**
 * @extends AbstractCommand<array<int, mixed>>
 */
final class PsubscribeCommand extends AbstractCommand
{
    public string $id { get => 'PSUBSCRIBE'; }
}
