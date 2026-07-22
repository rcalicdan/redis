<?php

declare(strict_types=1);

namespace Hibla\Redis\Handlers;

use Hibla\Redis\Enums\ConnectionState;
use Hibla\Redis\Exceptions\ConnectionException;
use Hibla\Redis\Exceptions\RedisException;
use Hibla\Redis\Internals\ConnectionContext;
use Hibla\Redis\Internals\Protocol\CommandBuilder;

/**
 * @internal
 */
final class CommandHandler
{
    public function __construct(
        private readonly ConnectionContext $ctx
    ) {
    }

    /**
     * Flushes all pending commands in the write queue to the TCP socket.
     */
    public function flush(): void
    {
        if ($this->ctx->state !== ConnectionState::READY || $this->ctx->socket === null) {
            return;
        }

        if ($this->ctx->writeQueue->isEmpty()) {
            return;
        }

        $payload = '';

        while (! $this->ctx->writeQueue->isEmpty()) {
            $request = $this->ctx->writeQueue->dequeue();

            // If the user cancelled the promise before the connection even sent it, skip it.
            if ($request->promise->isCancelled()) {
                continue;
            }

            $args = [$request->command->id, ...$request->command->arguments];
            $payload .= CommandBuilder::build($args);

            $this->ctx->responseQueue->enqueue($request);
        }

        if ($payload !== '') {
            $this->ctx->socket->write($payload);
        }
    }

    /**
     * Triggered by the Event Loop whenever Redis sends data back over the socket.
     */
    public function handleData(string $chunk): void
    {
        $this->ctx->parser->append($chunk);

        $result = null;
        while ($this->ctx->parser->parse($result)) {
            // PUB/SUB MESSAGE INTERCEPTION
            if (\is_array($result) && isset($result[0]) && \is_string($result[0])) {
                $type = strtolower($result[0]);

                if ($type === 'message' || $type === 'pmessage') {
                    if ($this->ctx->pubSubCallback !== null) {
                        /** @var array<int, mixed> $result */
                        ($this->ctx->pubSubCallback)($result);
                    }

                    continue; // Do NOT touch the responseQueue!
                }
            }

            if ($this->ctx->responseQueue->isEmpty()) {
                continue;
            }

            $request = $this->ctx->responseQueue->dequeue();
            if ($request->promise->isCancelled()) {
                continue;
            }

            if ($result instanceof RedisException) {
                $request->promise->reject($result);
            } else {
                try {
                    $formatted = $request->command->parseResponse($result);
                    $request->promise->resolve($formatted);
                } catch (\Throwable $e) {
                    $request->promise->reject($e);
                }
            }
        }
    }

    public function failPending(ConnectionException $exception): void
    {
        while (! $this->ctx->writeQueue->isEmpty()) {
            $this->ctx->writeQueue->dequeue()->promise->reject($exception);
        }

        while (! $this->ctx->responseQueue->isEmpty()) {
            $this->ctx->responseQueue->dequeue()->promise->reject($exception);
        }
    }
}
