<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Core\Inspection\Finding;
use Zana\Core\Inspection\SecurityRules;
use Zana\Core\Inspection\Severity;

/**
 * ADR-023: a finding's array form is the zana.check/1 JSON of one problem.
 */
final class FindingTest extends TestCase
{
    public function testTheArrayFormHasEveryFieldInOrder(): void
    {
        $finding = new Finding('routing.handler', Severity::Error, 'GET /about: its handler is not registered.', 'src/About.php', 12, 'Register it.');

        $this->assertSame([
            'id' => 'routing.handler',
            'severity' => 'error',
            'message' => 'GET /about: its handler is not registered.',
            'file' => 'src/About.php',
            'line' => 12,
            'hint' => 'Register it.',
            'security' => false,
        ], $finding->toArray());
        $this->assertTrue((new Finding('security.route_access', Severity::Error, 'x', security: true))->toArray()['security'], '2.21: a security rule says so');
    }

    public function testSecurityRulesAreErrorsOnlyInStrictMode(): void
    {
        $app = static fn (array $config) => Application::configure(sys_get_temp_dir() . '/zana-core-strict')->withEnvironment('testing')->withConfiguration($config)->build();

        $this->assertSame(Severity::Warning, SecurityRules::severity($app([])), 'off by default, so an upgrade fails nobody (ADR-003)');
        $this->assertSame(Severity::Error, SecurityRules::severity($app(['security.strict' => true])));
        $this->assertSame(Severity::Warning, SecurityRules::severity($app(['security.strict' => true]), Severity::Warning), 'a rule with false alarms stays a warning');
        $this->assertFalse(SecurityRules::strict($app(['security.strict' => false])));
    }

    public function testOptionalFieldsAreNull(): void
    {
        $this->assertSame(
            ['id' => 'shop.hours', 'severity' => 'warning', 'message' => 'Not set.', 'file' => null, 'line' => null, 'hint' => null, 'security' => false],
            (new Finding('shop.hours', Severity::Warning, 'Not set.'))->toArray(),
        );
        $this->assertSame(['error', 'warning', 'info'], array_map(static fn (Severity $s): string => $s->value, Severity::cases()));
    }
}
