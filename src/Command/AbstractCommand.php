<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

use Hibla\Redis\Interfaces\CommandInterface;

/**
 * Base representation of a Redis command.
 *
 * Provides default implementations of argument storage, non-blocking behavior,
 * and a simple pass-through response parser. Custom command implementations should
 * extend this class and declare their expected response type via generic template.
 *
 * @template-covariant TResponse
 *
 * @implements CommandInterface<TResponse>
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
     *
     * @return TResponse
     */
    public function parseResponse(mixed $data): mixed
    {
        /** @var TResponse */
        return $data; // Pass-through by default
    }
}
