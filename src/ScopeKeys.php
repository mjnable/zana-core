<?php

declare(strict_types=1);

namespace Zana\Core;

/**
 * Well-known ScopeMetadata attribute keys shared by Zana packages.
 */
final class ScopeKeys
{
    public const CORRELATION_ID = 'correlation_id';

    public const REQUEST_ID = 'request_id';

    public const USER_ID = 'user_id';

    public const TENANT_ID = 'tenant_id';

    private function __construct()
    {
    }
}
