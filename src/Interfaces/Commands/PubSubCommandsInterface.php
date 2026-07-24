<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces\Commands;

use Hibla\Promise\Interfaces\PromiseInterface;

interface PubSubCommandsInterface
{
    /**
     * Posts a message payload to specified channel.
     *
     * @param string $channel Target channel.
     * @param string $message Message payload.
     *
     * @return PromiseInterface<int> Number of clients that received message.
     */
    public function publish(string $channel, string $message): PromiseInterface;
}
