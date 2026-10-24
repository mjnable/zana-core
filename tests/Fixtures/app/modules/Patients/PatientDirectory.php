<?php

declare(strict_types=1);

namespace Modules\Patients;

use Zana\Configuration\Config;

final class PatientDirectory
{
    public function __construct(#[Config('patients.settings.max')] public readonly int $max)
    {
    }
}
