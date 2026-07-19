<?php

declare(strict_types=1);

namespace Hibla\Redis\Command;

final class HgetallCommand extends AbstractCommand
{
    public string $id { 
        get => 'HGETALL'; 
    }

    public function parseResponse(mixed $data): mixed
    {
        if (! \is_array($data)) {
            return $data; // Return as-is if it's an error or unexpected type
        }

        $result = [];
        $count = \count($data);

        for ($i = 0; $i < $count; $i += 2) {
            if (isset($data[$i + 1])) {
                $result[(string) $data[$i]] = $data[$i + 1];
            }
        }

        return $result;
    }
}