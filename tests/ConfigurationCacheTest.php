<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Core\ApplicationException;
use Zana\Core\Environment;

/**
 * ADR-019: in production, the configuration files' values and the module
 * list come from one cache file; the environment and overrides stay live.
 */
final class ConfigurationCacheTest extends TestCase
{
    private string $base = '';

    private string $cache = '';

    protected function setUp(): void
    {
        $this->base = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-core-config-cache-' . bin2hex(random_bytes(6));
        $this->cache = "{$this->base}/var/cache/config.php";
        $this->write('config/site.php', ['name' => 'Acme', 'url' => 'https://acme.example', 'mode' => Environment::class]);
        $this->write('config/production/site.php', ['url' => 'https://www.acme.example']);
        file_put_contents("{$this->base}/config/modules.php", '<?php return [Modules\Patients\PatientsModule::class];');
    }

    #[After]
    public function cleanUp(): void
    {
        putenv('APP_SITE__NAME');
        exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $this->base)) : 'rm -rf ' . escapeshellarg($this->base));
    }

    public function testProductionWritesTheCacheThenTrustsIt(): void
    {
        $first = $this->boot('production');
        $this->assertFileExists($this->cache);
        $this->assertSame('https://www.acme.example', $first->config()->string('site.url'), 'config/production/ overrides config/');
        $this->assertSame(5, $first->config()->int('patients.settings.max'), 'module configuration is cached too');

        $this->write('config/site.php', ['name' => 'Changed', 'url' => 'https://changed.example']);
        file_put_contents("{$this->base}/config/modules.php", '<?php return [];');

        $second = $this->boot('production');
        $this->assertSame('Acme', $second->config()->string('site.name'), 'the files are not read again');
        $this->assertSame(['Patients'], array_map(static fn ($m): string => $m->name(), $second->modules()), 'the module list is cached');
    }

    public function testTheEnvironmentAndOverridesStayLive(): void
    {
        $this->boot('production');
        putenv('APP_SITE__NAME=From the server');

        $app = Application::configure($this->base)
            ->withEnvironment('production')
            ->withConfigurationCache($this->cache)
            ->withConfiguration(['site.url' => 'https://override.example'])
            ->withConfiguration(['app.deployment_checks' => false])
            ->build();

        $this->assertSame('From the server', $app->config()->string('site.name'));
        $this->assertSame('https://override.example', $app->config()->string('site.url'));
    }

    public function testOutsideProductionTheFilesAreAlwaysRead(): void
    {
        $this->boot('development');
        $this->assertFileDoesNotExist($this->cache);

        $this->write('config/site.php', ['name' => 'Changed']);
        $this->assertSame('Changed', $this->boot('development')->config()->string('site.name'));
    }

    public function testACacheForAnotherEnvironmentIsRewritten(): void
    {
        $this->assertSame($this->cache, $this->boot('staging')->compileConfigurationCache(), 'cache:warm writes for the current environment');
        $this->assertStringContainsString("'environment' => 'staging'", (string) file_get_contents($this->cache));

        $this->assertSame('https://www.acme.example', $this->boot('production')->config()->string('site.url'));
        $this->assertStringContainsString("'environment' => 'production'", (string) file_get_contents($this->cache));
    }

    public function testObjectsInConfigurationPreventTheCache(): void
    {
        file_put_contents("{$this->base}/config/clock.php", '<?php return ["now" => new DateTimeImmutable("2026-10-26")];');

        $app = $this->boot('production');
        $this->assertFileDoesNotExist($this->cache, 'the first boot runs without a cache');
        $this->assertInstanceOf(\DateTimeImmutable::class, $app->config()->get('clock.now'));

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('The configuration cannot be cached: clock.now is DateTimeImmutable.');
        $app->compileConfigurationCache();
    }

    public function testWithoutACacheThereIsNothingToCompile(): void
    {
        $app = Application::configure($this->base)->withEnvironment('production')->withConfiguration(['app.deployment_checks' => false])->build();

        $this->assertNull($app->configurationCache());
        $this->assertNull($app->compileConfigurationCache());
    }

    private function boot(string $environment): Application
    {
        return Application::configure($this->base)
            ->withEnvironment($environment)
            ->withConfigurationCache($this->cache)
            ->build();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function write(string $file, array $values): void
    {
        @mkdir(dirname("{$this->base}/{$file}"), 0777, true);
        file_put_contents("{$this->base}/{$file}", '<?php return ' . var_export($values, true) . ';');
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate("{$this->base}/{$file}", true);
        }
    }
}
