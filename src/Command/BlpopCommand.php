<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class BlpopCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id {
        get => 'BLPOP';
    }

    /**
     * {@inheritDoc}
     */
    public function isBlocking(): bool
    {
        return true;
    }
}
