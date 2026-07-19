<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Promise\Promise;
use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Internals\Protocol\RespParser;
use Hibla\Socket\Interfaces\ConnectionInterface as SocketConnection;
use SplQueue;

/**
 * @internal
 */
final class ConnectionContext
{
    public ConnectionState $state = ConnectionState::DISCONNECTED;

    public ?SocketConnection $socket = null;

    /**
     * Commands waiting to be written to the socket.
     *
     * @var SplQueue<CommandRequest>
     */
    public SplQueue $writeQueue;

    /**
     * Commands that have been written and are waiting for a response from Redis.
     *
     * @var SplQueue<CommandRequest>
     */
    public SplQueue $responseQueue;

    public RespParser $parser;

    /**
     * @var Promise<Connection>|null
     */
    public ?Promise $connectPromise = null;

    public function __construct()
    {
        $this->writeQueue = new SplQueue();
        $this->responseQueue = new SplQueue();
        $this->parser = new RespParser();
    }
}
