<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * @extends AbstractCommand<string>
 */
final class PingCommand extends AbstractCommand
{
    public string $id {
        get => 'PING';
    }
}
