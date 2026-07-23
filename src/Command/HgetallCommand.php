<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
 * Redis HGETALL command.
 *
 * Returns all fields and values of the hash stored at key. In the returned value,
 * every alternate element represents a field name and the following element represents
 * its value. This command parses the flat array response into an associative array.
 *
 * @see https://redis.io/commands/hgetall/
 *
 * @extends AbstractCommand<array<string, string>>
 */
final class HgetallCommand extends AbstractCommand
{
    public string $id {
        get => 'HGETALL';
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed
     */
    public function parseResponse(mixed $data): mixed
    {
        if ($data === 'QUEUED') {
            return 'QUEUED';
        }

        if (! \is_array($data)) {
            return [];
        }

        /** @var array<string, string> $result */
        $result = [];
        $count = \count($data);

        for ($i = 0; $i < $count; $i += 2) {
            if (isset($data[$i + 1])) {
                $key = $data[$i];
                $val = $data[$i + 1];

                $keyStr = \is_scalar($key) || $key instanceof \Stringable ? (string) $key : '';
                $valStr = \is_scalar($val) || $val instanceof \Stringable ? (string) $val : '';

                $result[$keyStr] = $valStr;
            }
        }

        return $result;
    }
}
