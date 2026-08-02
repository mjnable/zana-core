<?php

declare(strict_types=1);

namespace Zana\Core\Tests\Lifecycle;

use PHPUnit\Framework\TestCase;
use Zana\Core\Lifecycle\LifecycleManager;
use Zana\Core\Application;

class LifecycleManagerTest extends TestCase
{
    private LifecycleManager $manager;
    private Application $app;

    protected function setUp(): void
    {
        $this->manager = new LifecycleManager();
        $this->app = new Application(sys_get_temp_dir() . '/zana_lifecycle_' . uniqid());
    }

    public function testHookAndExecute(): void
    {
        $called = false;
        $this->manager->hook('boot', function ($app) use (&$called) {
            $called = true;
            $this->assertInstanceOf(Application::class, $app);
        });

        $this->manager->execute('boot', $this->app);

        $this->assertTrue($called);
    }

    public function testExecuteWithNoHooksIsNoOp(): void
    {
        $this->manager->execute('nonexistent', $this->app);
        $this->addToAssertionCount(1);
    }

    public function testMultipleHooksRunInOrder(): void
    {
        $order = [];
        $this->manager->hook('boot', function () use (&$order) {
            $order[] = 'first';
        });
        $this->manager->hook('boot', function () use (&$order) {
            $order[] = 'second';
        });

        $this->manager->execute('boot', $this->app);

        $this->assertEquals(['first', 'second'], $order);
    }

    public function testHooksAreEventSpecific(): void
    {
        $called = false;
        $this->manager->hook('boot', function () use (&$called) {
            $called = true;
        });

        $this->manager->execute('run', $this->app);

        $this->assertFalse($called);
    }

    public function testClear(): void
    {
        $called = false;
        $this->manager->hook('boot', function () use (&$called) {
            $called = true;
        });

        $this->manager->clear();
        $this->manager->execute('boot', $this->app);

        $this->assertFalse($called);
    }
}

