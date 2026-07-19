<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class PingCommand extends AbstractCommand
{
    public string $id {
        get => 'PING';
    }
}
