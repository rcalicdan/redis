<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class MgetCommand extends AbstractCommand
{
    public string $id {
        get => 'MGET';
    }
}
