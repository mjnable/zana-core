<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Core\Inspection\Finding;
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
        ], $finding->toArray());
    }

    public function testOptionalFieldsAreNull(): void
    {
        $this->assertSame(
            ['id' => 'shop.hours', 'severity' => 'warning', 'message' => 'Not set.', 'file' => null, 'line' => null, 'hint' => null],
            (new Finding('shop.hours', Severity::Warning, 'Not set.'))->toArray(),
        );
        $this->assertSame(['error', 'warning', 'info'], array_map(static fn (Severity $s): string => $s->value, Severity::cases()));
    }
}
