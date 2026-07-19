<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class DelCommand extends AbstractCommand
{
    public string $id { 
        get => 'DEL'; 
    }
}