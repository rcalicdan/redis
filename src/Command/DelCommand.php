<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * @extends AbstractCommand<int>
 */
final class DelCommand extends AbstractCommand
{
    public string $id {
        get => 'DEL';
    }
}
