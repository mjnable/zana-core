<?php

declare(strict_types=1);

namespace Zana\Core;

/**
 * Thrown when the application cannot be composed: invalid module lists,
 * unknown or circular module dependencies, invalid providers; and, as
 * Deployment\UnsafeDeploymentException, when a deployment check is fatal.
 */
class ApplicationException extends \RuntimeException
{
}
