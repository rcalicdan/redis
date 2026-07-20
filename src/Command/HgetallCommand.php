<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

/**
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
     * @return array<string, string>
     */
    public function parseResponse(mixed $data): mixed
    {
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
