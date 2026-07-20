<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * @extends AbstractCommand<string|null>
 */
final class GetCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    public string $id { get => 'GET'; }
}
