<?php

declare(strict_types=1);

namespace Modules\Billing;

use Modules\Patients\PatientDirectory;

final class Invoicer
{
    public function __construct(public readonly PatientDirectory $patients)
    {
    }
}
