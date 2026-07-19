<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

use Hibla\Redis\Interfaces\CommandInterface;

/**
 * Base class for all Redis commands.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * @param array<int|string, mixed> $args
     */
    public function __construct(
        protected array $args = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public array $arguments {
        get => $this->args;
    }

    /**
     * {@inheritDoc}
     */
    public function isBlocking(): bool
    {
        return false; // False by default, overridden by specific commands
    }

    /**
     * {@inheritDoc}
     */
    public function parseResponse(mixed $data): mixed
    {
        return $data; // Pass-through by default
    }
}
