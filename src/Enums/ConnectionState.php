<?php

declare(strict_types=1);

namespace Hibla\Redis\Enums;

enum ConnectionState: string
{
    case DISCONNECTED = 'disconnected';
    case CONNECTING = 'connecting';
    case READY = 'ready';
    case CLOSED = 'closed';
    case SUBSCRIBED = 'subscribed';
}
