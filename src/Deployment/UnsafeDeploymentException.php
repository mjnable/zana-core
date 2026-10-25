<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

use Zana\Core\ApplicationException;

/**
 * Thrown by ApplicationBuilder::build() when a deployment check is fatal.
 */
final class UnsafeDeploymentException extends ApplicationException
{
    /**
     * @param list<string> $problems
     */
    public static function because(array $problems): self
    {
        return new self("The application refuses to start:\n  - " . implode("\n  - ", $problems));
    }
}
