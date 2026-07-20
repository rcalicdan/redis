<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * @extends AbstractCommand<string>
 */
final class SetCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'SET'; }
}
