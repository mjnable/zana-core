<?php

declare(strict_types=1);

namespace Zana\Core\Inspection;

use Zana\Core\ApplicationInterface;

/**
 * A part of the application shown by the inspect command (ADR-023), such as
 * its routes or its migrations.
 *
 *     $services->singleton(RoutesSection::class)->tag(InspectionSection::TAG, RoutesSection::class);
 */
interface InspectionSection
{
    public const string TAG = 'core.inspection_sections';

    /**
     * The section's name, lowercase, e.g. "routes"; also the inspect command's argument.
     */
    public function name(): string;

    /**
     * Plain data (arrays, strings, numbers, booleans, null), encoded as JSON
     * as is. Never secrets.
     *
     * @return array<array-key, mixed>
     */
    public function inspect(ApplicationInterface $application): array;
}
