<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Container\ServiceCollection;
use Zana\Core\Correlation;

final class CorrelationTest extends TestCase
{
    public function testIdsAreGeneratedOrReused(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Correlation::generate());
        $this->assertNotSame(Correlation::generate(), Correlation::generate());

        $this->assertSame('trace-42', Correlation::from('trace-42'));
        foreach ([null, '', 'has space', str_repeat('a', 129), "line\nbreak"] as $invalid) {
            $this->assertFalse(Correlation::isValid($invalid));
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Correlation::from($invalid));
        }
    }

    public function testTheLogContextIsSetAndRestored(): void
    {
        $context = new FakeLogContext();
        $context->set('correlation_id', 'outer');
        $container = new ServiceCollection()->instance(Correlation::LOG_CONTEXT, $context)->build();

        $restore = Correlation::applyToLogContext($container, 'inner');
        $this->assertSame(['correlation_id' => 'inner'], $context->all());
        $restore();
        $this->assertSame(['correlation_id' => 'outer'], $context->all());

        $context->remove('correlation_id');
        $restore = Correlation::applyToLogContext($container, 'only');
        $restore();
        $this->assertSame([], $context->all(), 'an id that was not there before is removed');
    }

    public function testWithoutALogContextNothingHappens(): void
    {
        $restore = Correlation::applyToLogContext(new ServiceCollection()->build(), 'id');
        $restore();
        $this->addToAssertionCount(1);
    }
}

final class FakeLogContext
{
    /** @var array<string, string|int|float|bool|null> */
    private array $values = [];

    public function set(string $key, string|int|float|bool|null $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function all(): array
    {
        return $this->values;
    }
}
