<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

use Zana\Core\ApplicationException;
use Zana\Core\ApplicationInterface;

/**
 * Runs the deployment checks: the built-in ones and the services tagged
 * "core.deployment_checks". The builder runs them at startup; the doctor
 * command runs them on demand.
 */
final class DeploymentChecks
{
    /**
     * @return list<array{string, CheckResult}> check class => result
     */
    public static function run(ApplicationInterface $application, ?string $containerCache = null, ?string $configurationCache = null): array
    {
        $checks = [new BuiltInChecks($containerCache, $configurationCache)];
        foreach ($application->container()->tagged(DeploymentCheck::TAG) as $check) {
            if (!$check instanceof DeploymentCheck) {
                throw new ApplicationException(sprintf('Services tagged "%s" must implement %s; %s does not.', DeploymentCheck::TAG, DeploymentCheck::class, $check::class));
            }
            $checks[] = $check;
        }

        return array_map(static fn (DeploymentCheck $check): array => [$check::class, $check->check($application)], $checks);
    }
}
