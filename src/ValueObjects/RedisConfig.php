<?php

declare(strict_types=1);

namespace Hibla\Redis\ValueObjects;

use InvalidArgumentException;

final readonly class RedisConfig
{
    /**
     * @param string $host Hostname or IP of the Redis server.
     * @param int $port TCP port (default 6379).
     * @param string $username Redis ACL username (Redis 6+).
     * @param string $password Redis password.
     * @param int $database Redis logical database index (default 0).
     * @param int $connectTimeout Seconds before a connect attempt is aborted.
     * @param bool $ssl Whether to use TLS/SSL for the connection (rediss://).
     */
    public function __construct(
        public string $host,
        public int $port = 6379,
        public string $username = '',
        public string $password = '',
        public int $database = 0,
        public int $connectTimeout = 10,
        public bool $ssl = false,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $host = $config['host'] ?? throw new InvalidArgumentException('Host is required');
        if (! \is_string($host)) {
            throw new InvalidArgumentException('Host must be a string');
        }

        $port = isset($config['port']) && is_numeric($config['port']) ? (int) $config['port'] : 6379;
        $username = isset($config['username']) && \is_scalar($config['username']) ? (string) $config['username'] : '';
        $password = isset($config['password']) && \is_scalar($config['password']) ? (string) $config['password'] : '';
        $database = isset($config['database']) && is_numeric($config['database']) ? (int) $config['database'] : 0;
        $connectTimeout = isset($config['connect_timeout']) && is_numeric($config['connect_timeout']) ? (int) $config['connect_timeout'] : 10;
        $ssl = isset($config['ssl']) && \is_scalar($config['ssl']) ? (bool) $config['ssl'] : false;

        return new self(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            database: $database,
            connectTimeout: $connectTimeout,
            ssl: $ssl,
        );
    }

    public static function fromUri(string $uri): self
    {
        if (! str_contains($uri, '://')) {
            $uri = 'redis://' . $uri;
        }

        $parts = parse_url($uri);

        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid Redis URI: ' . $uri);
        }

        $scheme = $parts['scheme'] ?? 'redis';
        if ($scheme !== 'redis' && $scheme !== 'rediss') {
            throw new InvalidArgumentException('Invalid URI scheme "' . $scheme . '", expected "redis" or "rediss"');
        }

        $ssl = $scheme === 'rediss';
        $port = isset($parts['port']) ? (int) $parts['port'] : 6379;
        $username = isset($parts['user']) ? rawurldecode((string) $parts['user']) : '';
        $password = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '';

        $database = 0;
        if (isset($parts['path'])) {
            $path = ltrim((string) $parts['path'], '/');
            if ($path !== '' && is_numeric($path)) {
                $database = (int) $path;
            }
        }

        $query = [];
        if (isset($parts['query']) && \is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $connectTimeout = isset($query['connect_timeout']) && is_numeric($query['connect_timeout'])
            ? (int) $query['connect_timeout']
            : 10;

        return new self(
            host: (string) $parts['host'],
            port: $port,
            username: $username,
            password: $password,
            database: $database,
            connectTimeout: $connectTimeout,
            ssl: $ssl,
        );
    }

    public function toSafeUri(): string
    {
        $uri = $this->ssl ? 'rediss://' : 'redis://';

        if ($this->username !== '' || $this->password !== '') {
            $uri .= $this->username !== '' ? rawurlencode($this->username) : '';
            $uri .= ':***@';
        }

        $uri .= $this->host . ':' . $this->port;

        if ($this->database !== 0) {
            $uri .= '/' . $this->database;
        }

        return $uri;
    }
}
