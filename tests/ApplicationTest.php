<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Core\Exceptions\ApplicationException;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Configuration\Contracts\ConfigurationInterface;

class ApplicationTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/zana_app_' . uniqid();
        mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        $configDir = $this->basePath . '/config';
        if (is_dir($configDir)) {
            foreach (glob($configDir . '/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($configDir);
        }
        rmdir($this->basePath);
    }

    public function testConstructWithDefaults(): void
    {
        $app = new Application($this->basePath);

        $this->assertSame('development', $app->environment());
        $this->assertTrue($app->isDebug());
        $this->assertFalse($app->isBooted());
        $this->assertFalse($app->isRunning());
    }

    public function testConstructWithCustomEnvironment(): void
    {
        $app = new Application($this->basePath, 'production', false);

        $this->assertSame('production', $app->environment());
        $this->assertFalse($app->isDebug());
    }

    public function testIsEnvironment(): void
    {
        $app = new Application($this->basePath, 'testing');

        $this->assertTrue($app->isEnvironment('testing'));
        $this->assertTrue($app->isEnvironment('testing', 'production'));
        $this->assertFalse($app->isEnvironment('production'));
    }

    public function testGetContainerThrowsBeforeBoot(): void
    {
        $this->expectException(ApplicationException::class);

        $app = new Application($this->basePath);
        $app->getContainer();
    }

    public function testGetConfiguration(): void
    {
        $app = new Application($this->basePath);
        $config = $app->getConfiguration();

        $this->assertInstanceOf(ConfigurationInterface::class, $config);
    }

    public function testBasePath(): void
    {
        $app = new Application($this->basePath);

        $this->assertSame(rtrim($this->basePath, '/\\'), $app->basePath());
        $this->assertSame($app->basePath() . DIRECTORY_SEPARATOR . 'app', $app->basePath('app'));
    }

    public function testConfigPath(): void
    {
        $app = new Application($this->basePath);

        $this->assertSame(
            $app->basePath() . DIRECTORY_SEPARATOR . 'config',
            $app->configPath()
        );
    }

    public function testBoot(): void
    {
        $app = new Application($this->basePath);
        $app->boot();

        $this->assertTrue($app->isBooted());
        $this->assertInstanceOf(ContainerInterface::class, $app->getContainer());
    }

    public function testBootIsIdempotent(): void
    {
        $app = new Application($this->basePath);
        $app->boot();
        $container = $app->getContainer();
        $app->boot();

        $this->assertSame($container, $app->getContainer());
    }

    public function testRunWithoutKernel(): void
    {
        $app = new Application($this->basePath);
        $result = $app->run();

        $this->assertNull($result);
        $this->assertTrue($app->isBooted());
    }

    public function testRunThrowsWhenAlreadyRunning(): void
    {
        $app = new Application($this->basePath);
        $app->boot();
        $app->run();

        // Running flag is reset in finally, so second run should work
        $result = $app->run();
        $this->assertNull($result);
    }

    public function testTerminateBeforeBootIsNoOp(): void
    {
        $app = new Application($this->basePath);
        $app->terminate();

        $this->assertFalse($app->isBooted());
    }

    public function testTerminateAfterBoot(): void
    {
        $app = new Application($this->basePath);
        $app->boot();
        $app->terminate();

        $this->assertFalse($app->isBooted());
        $this->assertFalse($app->isRunning());
    }

    public function testRegisterProvider(): void
    {
        $app = new Application($this->basePath);
        $provider = new class() extends \Zana\Core\Providers\ServiceProvider {
            public function register(\Zana\Container\ServiceCollection $services): void
            {
                $services->singleton('test.service', fn() => new \stdClass());
            }
        };

        $app->register($provider);
        $app->boot();

        $this->assertInstanceOf(\stdClass::class, $app->getContainer()->get('test.service'));
    }

    public function testRegisterProviders(): void
    {
        $app = new Application($this->basePath);
        $provider = new class() extends \Zana\Core\Providers\ServiceProvider {
            public function register(\Zana\Container\ServiceCollection $services): void
            {
                $services->singleton('test.service', fn() => new \stdClass());
            }
        };

        $app->registerProviders([$provider]);
        $app->boot();

        $this->assertTrue($app->getContainer()->has('test.service'));
    }

    public function testRegisterThrowsAfterBoot(): void
    {
        $this->expectException(ApplicationException::class);

        $app = new Application($this->basePath);
        $app->boot();

        $provider = new class() extends \Zana\Core\Providers\ServiceProvider {
            public function register(\Zana\Container\ServiceCollection $services): void
            {
            }
        };
        $app->register($provider);
    }

    public function testLoadConfiguration(): void
    {
        $configDir = $this->basePath . '/config';
        mkdir($configDir);
        file_put_contents(
            $configDir . '/app.php',
            '<?php return ["name" => "Zana", "debug" => true];'
        );

        $app = new Application($this->basePath);
        $app->loadConfiguration();

        $this->assertEquals('Zana', $app->getConfiguration()->get('app.name'));
        $this->assertTrue($app->getConfiguration()->get('app.debug'));
    }

    public function testLoadConfigurationWithMissingDirectoryIsNoOp(): void
    {
        $app = new Application($this->basePath);
        $app->loadConfiguration($this->basePath . '/nonexistent');

        $this->assertEmpty($app->getConfiguration()->all());
    }
}

