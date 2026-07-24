<?php

declare(strict_types=1);

namespace Hibla\Redis\Interfaces;

use Hibla\Redis\Interfaces\Commands\ConnectionCommandsInterface;
use Hibla\Redis\Interfaces\Commands\HashesCommandsInterface;
use Hibla\Redis\Interfaces\Commands\KeysCommandsInterface;
use Hibla\Redis\Interfaces\Commands\ListsCommandsInterface;
use Hibla\Redis\Interfaces\Commands\PubSubCommandsInterface;
use Hibla\Redis\Interfaces\Commands\SetsCommandsInterface;
use Hibla\Redis\Interfaces\Commands\SortedSetsCommandsInterface;
use Hibla\Redis\Interfaces\Commands\StringsCommandsInterface;

/**
 * Composite contract aggregating all standard Redis domain command interfaces.
 */
interface RedisCommandsInterface extends
    ConnectionCommandsInterface,
    HashesCommandsInterface,
    KeysCommandsInterface,
    ListsCommandsInterface,
    PubSubCommandsInterface,
    SetsCommandsInterface,
    SortedSetsCommandsInterface,
    StringsCommandsInterface
{
}