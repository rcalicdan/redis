<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * @extends AbstractCommand<array<int, string|null>>
 */
final class MgetCommand extends AbstractCommand
{
    public string $id {
        get => 'MGET';
    }
}
