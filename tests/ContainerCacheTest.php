<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Exception\StaleCompilationException;
use Zana\Core\Application;
use Zana\Core\ApplicationException;
use Zana\Core\Tests\Fixtures\Clock;
use Zana\Core\Tests\Fixtures\FrameworkProvider;

final class ContainerCacheTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/zana-core-cache-' . bin2hex(random_bytes(6)) . '/container.php';
    }

    #[After]
    public function cleanUp(): void
    {
        @unlink($this->file);
        @rmdir(dirname($this->file));
    }

    public function testTheCacheIsWrittenThenReused(): void
    {
        $first = $this->app('development', FrameworkProvider::class);
        $this->assertFileExists($this->file);
        $this->assertInstanceOf(Clock::class, $first->container()->get(Clock::class));

        $written = (string) file_get_contents($this->file);

        $second = $this->app('development', FrameworkProvider::class);
        $this->assertInstanceOf(Clock::class, $second->container()->get(Clock::class));
        $this->assertSame($written, file_get_contents($this->file), 'a fresh cache is reused as is');
    }

    public function testStaleCachesAreRebuiltOutsideProduction(): void
    {
        $this->app('development');
        $stale = (string) file_get_contents($this->file);

        $app = $this->app('development', FrameworkProvider::class);

        $this->assertNotSame($stale, file_get_contents($this->file));
        $this->assertTrue($app->container()->has(Clock::class));
    }

    public function testStaleCachesFailInProduction(): void
    {
        $this->app('production');

        $this->expectException(StaleCompilationException::class);
        $this->app('production', FrameworkProvider::class);
    }

    public function testConsolesMayRecompileAStaleCacheInProduction(): void
    {
        $this->app('production');

        $app = Application::configure(sys_get_temp_dir() . '/zana-core-cache-app')
            ->withEnvironment('production')
            ->withProviders(FrameworkProvider::class)
            ->withServices(static fn (ServiceCollectionInterface $s) => $s->transient('closure', static fn (): Clock => new Clock()))
            ->withContainerCache($this->file)
            ->recompileStaleContainerInConsole()
            ->build();

        $this->assertTrue($app->container()->has(Clock::class));
    }

    public function testTheCacheCanBeCompiledWithoutBooting(): void
    {
        $app = $this->app('development', FrameworkProvider::class);
        unlink($this->file);

        $this->assertSame($this->file, $app->compileContainerCache());
        $this->assertFileExists($this->file);
        $this->assertSame($this->file, $app->containerCache());
        $this->assertNull(Application::configure(sys_get_temp_dir() . '/zana-core-cache-app')->withEnvironment('testing')->build()->compileContainerCache(), 'no cache');
    }

    public function testTheCacheFileReturnsItsClassName(): void
    {
        $this->app('development');

        $this->assertMatchesRegularExpression('/^return \\\\Zana\\\\CompiledContainer\\\\Container[0-9a-f]{16}::class;$/m', (string) file_get_contents($this->file));
    }

    public function testCacheFilesWithoutTheReturnStatementStillLoad(): void
    {
        $this->app('production', FrameworkProvider::class);
        $source = (string) file_get_contents($this->file);
        $older = (string) preg_replace('/^return .*$/m', '', str_replace('final class Container', 'final class Older', $source));
        file_put_contents($this->file, $older);

        $app = (new \ReflectionClass(\Zana\Core\ApplicationBuilder::class))->getProperty('compiled');
        $app->setValue(null, []);   // as in a new process

        $this->assertInstanceOf(Clock::class, $this->app('production', FrameworkProvider::class)->container()->get(Clock::class));
        $this->assertSame($older, file_get_contents($this->file), 'loaded, not rewritten');
    }

    public function testAFileRewrittenByAnotherProcessIsPickedUp(): void
    {
        $this->app('development');                                    // this process loads the cache

        $file = $this->file;
        $this->file = dirname($file) . '/other.php';                  // "another process" compiles a new one
        $this->app('production', FrameworkProvider::class);
        copy($this->file, $file);
        unlink($this->file);
        $this->file = $file;

        $app = $this->app('production', FrameworkProvider::class);    // stale in memory, fresh on disk

        $this->assertTrue($app->container()->has(Clock::class));
    }

    public function testForeignCacheFilesAreRejected(): void
    {
        @mkdir(dirname($this->file), 0777, true);
        file_put_contents($this->file, '<?php return [];');

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('is not a compiled container');
        $this->app('development');
    }

    /**
     * @param class-string<FrameworkProvider> ...$providers
     */
    private function app(string $environment, string ...$providers): Application
    {
        return Application::configure(sys_get_temp_dir() . '/zana-core-cache-app')
            ->withEnvironment($environment)
            ->withProviders(...$providers)
            ->withServices(static fn (ServiceCollectionInterface $s) => $s->transient('closure', static fn (): Clock => new Clock()))
            ->withContainerCache($this->file)
            ->build();
    }
}
