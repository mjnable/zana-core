<?php

declare(strict_types=1);

namespace Zana\Core\Inspection;

use Zana\Core\ApplicationInterface;

/**
 * A check of the application's correctness, run by the check command
 * (ADR-023). Unlike a deployment check it never runs at startup, so it may
 * be slow or touch the database.
 *
 *     $services->singleton(HandlersRegistered::class)->tag(ApplicationCheck::TAG, HandlersRegistered::class);
 */
interface ApplicationCheck
{
    public const string TAG = 'core.application_checks';

    /**
     * @return list<Finding> Nothing when all is well
     */
    public function findings(ApplicationInterface $application): array;
}
