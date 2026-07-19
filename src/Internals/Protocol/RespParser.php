<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals\Protocol;

use Hibla\Redis\Exceptions\IncompleteBufferException;
use Hibla\Redis\Exceptions\RedisException;
use RuntimeException;

/**
 * @internal
 */
final class RespParser
{
    private string $buffer = '';

    private int $offset = 0;

    public function append(string $chunk): void
    {
        $this->buffer .= $chunk;
    }

    /**
     * Attempts to parse a complete RESP message.
     *
     * @param mixed &$result Populated with the parsed value if successful.
     *
     * @return bool True if a full message was parsed, false if more data is needed.
     */
    public function parse(mixed &$result): bool
    {
        if ($this->buffer === '') {
            return false;
        }

        $this->offset = 0;

        try {
            $result = $this->parseType();

            // Success: Trim the buffer
            $this->buffer = substr($this->buffer, $this->offset);

            return true;
        } catch (IncompleteBufferException) {
            // Wait for next socket chunk
            return false;
        }
    }

    private function parseType(): mixed
    {
        if (! isset($this->buffer[$this->offset])) {
            throw new IncompleteBufferException();
        }

        $type = $this->buffer[$this->offset++];

        return match ($type) {
            '+' => $this->readLine(),
            '-' => new RedisException($this->readLine()),
            ':' => (int) $this->readLine(),
            '$' => $this->parseBulkString(),
            '*' => $this->parseArray(),
            default => throw new RuntimeException("Protocol error, unknown RESP type: $type"),
        };
    }

    private function readLine(): string
    {
        $pos = strpos($this->buffer, "\r\n", $this->offset);

        if ($pos === false) {
            throw new IncompleteBufferException();
        }

        $line = substr($this->buffer, $this->offset, $pos - $this->offset);
        $this->offset = $pos + 2;

        return $line;
    }

    private function parseBulkString(): ?string
    {
        $length = (int) $this->readLine();

        if ($length === -1) {
            return null;
        }

        if (\strlen($this->buffer) < $this->offset + $length + 2) {
            throw new IncompleteBufferException();
        }

        $string = substr($this->buffer, $this->offset, $length);
        $this->offset += $length + 2;

        return $string;
    }

    /**
     * @return list<mixed>|null
     */
    private function parseArray(): ?array
    {
        $count = (int) $this->readLine();

        if ($count === -1) {
            return null;
        }

        $array = [];
        for ($i = 0; $i < $count; $i++) {
            $array[] = $this->parseType();
        }

        return $array;
    }
}
