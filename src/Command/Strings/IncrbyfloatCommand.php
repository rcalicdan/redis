<?php

declare(strict_types=1);

namespace Hibla\Redis\Command\Strings;

use Hibla\Redis\Command\AbstractCommand;

/**
 * Redis INCRBYFLOAT command.
 *
 * Increments the string representing a floating point number stored at key by specified increment.
 *
 * @see https://redis.io/commands/incrbyfloat/
 *
 * @extends AbstractCommand<float>
 */
final class IncrbyfloatCommand extends AbstractCommand
{
    public string $id {
        get => 'INCRBYFLOAT';
    }

    /**
     * {@inheritDoc}
     */
    public function parseResponse(mixed $data): float
    {
        return (float) $data;
    }
}
