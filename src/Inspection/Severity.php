<?php

declare(strict_types=1);

namespace Zana\Core\Inspection;

/**
 * How serious a finding is: an error fails the check command (exit code 1).
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
