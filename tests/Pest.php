<?php

declare(strict_types=1);

use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Handlers\CommandHandler;
use Hibla\Redis\Internals\ConnectionContext;
use Hibla\Redis\ValueObjects\RedisConfig;
use Hibla\Socket\Interfaces\ConnectionInterface;

uses()->afterEach(function (): void {
    Mockery::close();
})->in(__DIR__);

function createHandler(): array
{
    $ctx = new ConnectionContext();
    $ctx->state = ConnectionState::READY;

    $socket = Mockery::mock(ConnectionInterface::class);
    $ctx->socket = $socket;

    $handler = new CommandHandler($ctx);

    return [$handler, $ctx, $socket];
}

/**
 * @param array<string, mixed> $overrides
 */
function getConfig(array $overrides = []): RedisConfig
{
    $defaults = [
        'host' => getenv('REDIS_HOST') !== false ? (string) getenv('REDIS_HOST') : '127.0.0.1',
        'port' => getenv('REDIS_PORT') !== false ? (int) getenv('REDIS_PORT') : 6379,
        'password' => getenv('REDIS_PASSWORD') !== false ? (string) getenv('REDIS_PASSWORD') : 'root_password',
        'database' => getenv('REDIS_DATABASE') !== false ? (int) getenv('REDIS_DATABASE') : 0,
    ];

    return RedisConfig::fromArray(array_merge($defaults, $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function getSslConfig(array $overrides = []): RedisConfig
{
    $defaults = [
        'host' => getenv('REDIS_SSL_HOST') !== false ? (string) getenv('REDIS_SSL_HOST') : '127.0.0.1',
        'port' => getenv('REDIS_SSL_PORT') !== false ? (int) getenv('REDIS_SSL_PORT') : 6380,
        'password' => getenv('REDIS_PASSWORD') !== false ? (string) getenv('REDIS_PASSWORD') : 'root_password',
        'database' => getenv('REDIS_DATABASE') !== false ? (int) getenv('REDIS_DATABASE') : 0,
        'ssl' => true,
        'ssl_verify' => false,
    ];

    return RedisConfig::fromArray(array_merge($defaults, $overrides));
}
