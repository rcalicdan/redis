<?php

declare(strict_types=1);

namespace Hibla\Redis\Internals;

use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * @internal Tracks in-flight state for a single transaction() call so it can be
 * shared between the fiber body and its onCancel handler without loose references.
 */
final class TransactionExecutionState
{
    public bool $isCancelled = false;

    public ?RedisTransaction $activeTx = null;

    /**
     * @var PromiseInterface<Connection>|null
     */
    public ?PromiseInterface $poolPromise = null;

    /**
     * @var PromiseInterface<mixed>|null
     */
    public ?PromiseInterface $innerWorkPromise = null;
}
