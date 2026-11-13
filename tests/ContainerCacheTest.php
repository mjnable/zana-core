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
            ->withContainerCache($this->file, always: true)   // the cache's mechanics, in every environment
            ->build();
    }

    public function testOutsideProductionTheCacheIsNeitherReadNorWritten(): void
    {
        foreach (['development', 'testing'] as $environment) {
            Application::configure(sys_get_temp_dir() . '/zana-core-cache-app')->withEnvironment($environment)->withContainerCache($this->file)->build();
            $this->assertFileDoesNotExist($this->file, $environment);
        }

        Application::configure(sys_get_temp_dir() . '/zana-core-cache-app')->withEnvironment('production')->withContainerCache($this->file)->build();
        $this->assertFileExists($this->file, 'production keeps its cache');
    }

    /**
     * FE-70 as it happened: a registered handler gains a constructor parameter between two requests.
     */
    public function testAChangedConstructorWorksAtTheNextRequestInDevelopment(): void
    {
        $results = [];
        foreach (['development' => 'works', 'production' => 'a stale cache, as before: cache:warm is the answer'] as $environment => $_) {
            $folder = dirname($this->file) . "/{$environment}";
            @mkdir($folder, 0777, true);
            $results[$environment] = [
                $this->request($folder, $environment, 'public function __construct(public Clock $clock) {}'),
                $this->request($folder, $environment, 'public function __construct(public Clock $clock, public \ArrayObject $orders) {}'),
            ];
        }

        $this->assertSame(['ok', 'ok'], $results['development'], 'the second request sees the new constructor');
        $this->assertSame('ok', $results['production'][0]);
        $this->assertStringContainsString('ArgumentCountError', $results['production'][1], 'production is unchanged');
    }

    /**
     * One request in its own process, as the front controller boots: the handler's class as it is at that moment.
     */
    private function request(string $folder, string $environment, string $constructor): string
    {
        $suffix = bin2hex(random_bytes(4));
        file_put_contents("{$folder}/Handler.php", "<?php\nnamespace App;\nuse Zana\\Core\\Tests\\Fixtures\\Clock;\nfinal class PlaceOrderHandler { {$constructor} }\n");
        $script = "{$folder}/request-{$suffix}.php";
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
require __DIR__ . "/Handler.php";
try {
    $app = Zana\Core\Application::configure(__DIR__)->withEnvironment(' . var_export($environment, true) . ')
        ->withServices(static fn ($s) => $s->singleton(Zana\Core\Tests\Fixtures\Clock::class)->singleton(\ArrayObject::class)->transient(App\PlaceOrderHandler::class))
        ->withContainerCache(__DIR__ . "/container.php")->build();
    $app->container()->get(App\PlaceOrderHandler::class);
    echo "ok";
} catch (Throwable $e) {
    for ($c = $e; $c !== null; $c = $c->getPrevious()) { echo get_class($c), ": ", $c->getMessage(), "\n"; }
}');
        $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d xdebug.mode=off ' . escapeshellarg($script) . ' 2>&1');
        unlink($script);

        return trim($output);
    }
}
