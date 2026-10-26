<?php

declare(strict_types=1);

namespace Zana\Core\Cache;

use Zana\Core\ApplicationInterface;

/**
 * Fills a cache before traffic arrives, e.g. compiled templates. Tag
 * implementations "core.cache_warmers"; the cache:warm command runs them.
 */
interface CacheWarmerInterface
{
    public const string TAG = 'core.cache_warmers';

    /**
     * @return list<string> What was warmed, for the command's output
     */
    public function warm(ApplicationInterface $application): array;
}
