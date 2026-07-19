<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class SetCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'SET'; }
}
