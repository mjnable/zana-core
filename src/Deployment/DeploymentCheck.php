<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

use Zana\Core\ApplicationInterface;

/**
 * A check the application runs at startup, after the boot phase (core
 * specification, 2.1 additions). A fatal result stops the build.
 *
 * Register application checks as services tagged "core.deployment_checks":
 *
 *     $services->singleton(UploadsWritable::class)->tag(DeploymentCheck::TAG, UploadsWritable::class);
 */
interface DeploymentCheck
{
    public const string TAG = 'core.deployment_checks';

    public function check(ApplicationInterface $application): CheckResult;
}
