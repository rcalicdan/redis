<?php

declare(strict_types=1);

namespace Hibla\Redis\Traits\Commands;

/**
 * Master trait aggregating all domain-specific Redis command traits.
 */
trait RedisCommandsTrait
{
    use ConnectionCommandsTrait;
    use HashesCommandsTrait;
    use KeysCommandsTrait;
    use ListsCommandsTrait;
    use PubSubCommandsTrait;
    use SetsCommandsTrait;
    use SortedSetsCommandsTrait;
    use StringsCommandsTrait;
}
