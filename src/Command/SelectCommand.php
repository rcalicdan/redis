<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class SelectCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'SELECT'; }
}
