<?php

declare(strict_types=1);

namespace Zana\Core\Tests\Fixtures;

/**
 * Records lifecycle calls made by fixture providers and modules.
 */
final class Journal
{
    /** @var list<string> */
    public static array $entries = [];
}
