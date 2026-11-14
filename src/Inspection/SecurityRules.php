<?php

declare(strict_types=1);

namespace Zana\Core\Inspection;

use Zana\Core\ApplicationInterface;

/**
 * Strict mode for the security rules of `check` (2.21, FE-68, ADR-033).
 *
 * Each package checks what it owns, and reports a broken rule as a warning,
 * or, with security.strict, as an error: `check` then fails, and
 * release:build refuses to build. Strict is off by default, so an upgrade
 * never fails a project's CI (ADR-003); the skeletons turn it on.
 *
 *     new Finding('security.route_access', SecurityRules::severity($application), '…', security: true);
 */
final class SecurityRules
{
    public const string STRICT = 'security.strict';

    public static function strict(ApplicationInterface $application): bool
    {
        return $application->config()->bool(self::STRICT, false);
    }

    /**
     * An error in strict mode, a warning otherwise. Rules with false alarms
     * pass $always = Severity::Warning and stay warnings.
     */
    public static function severity(ApplicationInterface $application, ?Severity $always = null): Severity
    {
        return $always ?? (self::strict($application) ? Severity::Error : Severity::Warning);
    }
}
