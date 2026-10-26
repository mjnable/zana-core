<?php

declare(strict_types=1);

namespace Zana\Core\Cache;

use Zana\Core\ApplicationInterface;

/**
 * Clears a cache the application writes, such as compiled templates. Tag
 * implementations "core.cache_clearers"; the cache:clear command runs them.
 */
interface CacheClearerInterface
{
    public const string TAG = 'core.cache_clearers';

    /**
     * @return list<string> What was cleared, for the command's output
     */
    public function clear(ApplicationInterface $application): array;
}
