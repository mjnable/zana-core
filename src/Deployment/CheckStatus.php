<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

enum CheckStatus
{
    case Ok;
    /** Logged; the application starts. */
    case Warning;
    /** The application refuses to start. */
    case Fatal;
}
