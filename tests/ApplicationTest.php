<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use Modules\Billing\BillingModule;
use Modules\Billing\Invoicer;
use Modules\Patients\PatientDirectory;
use Modules\Patients\PatientsModule;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Core\ApplicationException;
use Zana\Core\ApplicationInterface;
use Zana\Core\Environment;
use Zana\Core\Tests\Fixtures\AlphaModule;
use Zana\Core\Tests\Fixtures\BetaModule;
use Zana\Core\Tests\Fixtures\Clock;
use Zana\Core\Tests\Fixtures\Connection;
use Zana\Core\Tests\Fixtures\DefaultOriginProvider;
use Zana\Core\Tests\Fixtures\FrameworkProvider;
use Zana\Core\Tests\Fixtures\GreedyProvider;
use Zana\Core\Tests\Fixtures\Journal;
use Zana\Core\Tests\Fixtures\OrphanModule;
use Zana\Core\Tests\Fixtures\ProfiledProvider;
use Zana\Core\Tests\Fixtures\StandaloneModule;

final class ApplicationTest extends TestCase
{
    private const VARIABLES = ['APP_ENV', 'APP_DEBUG', 'APP_APP__NAME', 'ZANA_CORE_FIXTURE_GREETING'];

    #[Before]
    public function reset(): void
    {
        Journal::$entries = [];
    }

    #[After]
    public function clearEnvironment(): void
    {
        foreach (self::VARIABLES as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    // ── environment ────────────────────────────────────────────────────────

    public function testEnvironmentDefaultsToProductionWithoutDebug(): void
    {
        $environment = $this->bare()->build()->environment();

        $this->assertSame(['production', false], [$environment->name, $environment->debug]);
        $this->assertTrue($environment->isProduction());
    }

    public function testEnvironmentComesFromAppEnvAndAppDebug(): void
    {
        putenv('APP_ENV=development');
        $this->assertSame(['development', true], [$this->bare()->build()->environment()->name, $this->bare()->build()->environment()->debug]);

        putenv('APP_DEBUG=false');
        $this->assertFalse($this->bare()->build()->environment()->debug);
    }

    public function testBuilderEnvironmentWins(): void
    {
        putenv('APP_ENV=development');

        $environment = $this->bare()->withEnvironment('testing')->build()->environment();

        $this->assertTrue($environment->isTesting());
        $this->assertTrue($environment->is('testing'));
        $this->assertFalse($environment->isDevelopment());
    }

    // ── configuration ──────────────────────────────────────────────────────

    public function testConfigurationLayers(): void
    {
        putenv('APP_APP__NAME=FromEnvironment');

        $app = $this->fixture()->build();

        $this->assertSame('FromEnvironment', $app->config()->get('app.name'), 'environment variables win');
        $this->assertSame('hello from testing', $app->config()->get('app.greeting'), 'the environment directory overrides config/');
        $this->assertSame(['a', 'b'], $app->config()->get('app.features'));
        $this->assertSame(5, $app->config()->get('patients.settings.max'), 'module configuration');
        $this->assertFalse($app->config()->has('modules'), 'modules.php is not configuration');
    }

    public function testDotEnvIsLoadedWithoutOverridingRealVariables(): void
    {
        $this->fixture()->build();
        $this->assertSame('from-dotenv', getenv('ZANA_CORE_FIXTURE_GREETING'));

        putenv('ZANA_CORE_FIXTURE_GREETING=real');
        $this->fixture()->build();
        $this->assertSame('real', getenv('ZANA_CORE_FIXTURE_GREETING'));
    }

    // ── modules and providers ──────────────────────────────────────────────

    public function testModulesComeFromTheFileInDependencyOrder(): void
    {
        $app = $this->fixture()->withModules(PatientsModule::class)->build();

        $this->assertSame([PatientsModule::class, BillingModule::class], array_map(static fn ($m) => $m::class, $app->modules()));
        $this->assertInstanceOf(Invoicer::class, $app->container()->get(Invoicer::class));
        $this->assertSame(5, $app->container()->get(PatientDirectory::class)->max, '#[Config] works out of the box');
    }

    public function testProvidersRegisterThenModulesThenServicesAndBootInTheSameOrder(): void
    {
        $this->fixture()
            ->withProviders(FrameworkProvider::class)
            ->withServices(static function (ServiceCollectionInterface $services): void {
                Journal::$entries[] = 'register:app';
            })
            ->build();

        $this->assertSame([
            'register:FrameworkProvider',
            'register:Patients',
            'register:Billing',
            'register:app',
            'boot:FrameworkProvider',
            'boot:Patients',
            'boot:Billing',
        ], Journal::$entries);
    }

    public function testRegistrationsCarryTheirOrigin(): void
    {
        $app = $this->fixture()
            ->withProviders(new FrameworkProvider(), DefaultOriginProvider::class)
            ->withServices(static fn (ServiceCollectionInterface $s) => $s->singleton('app-service', Clock::class))
            ->build();

        $origins = [];
        foreach ($app->container()->graph()->nodes() as $node) {
            $origins[$node->service] = $node->origin;
        }

        $this->assertSame('package:fixture/framework', $origins[Clock::class]);
        $this->assertSame('provider:' . DefaultOriginProvider::class, $origins['default-origin']);
        $this->assertSame('module:Patients', $origins[PatientDirectory::class]);
        $this->assertSame('module:Billing', $origins[Invoicer::class]);
        $this->assertSame('app', $origins['app-service']);
        $this->assertSame('zana/core', $origins[ConfigurationInterface::class]);
    }

    public function testTheEnvironmentIsTheActiveProfile(): void
    {
        $this->assertTrue($this->bare()->withEnvironment('testing')->withProviders(ProfiledProvider::class)->build()->container()->has('testing-only'));
        $this->assertFalse($this->bare()->withEnvironment('production')->withProviders(ProfiledProvider::class)->build()->container()->has('testing-only'));
    }

    public function testCoreServicesAreRegistered(): void
    {
        $app = $this->fixture()->build();
        $container = $app->container();

        $this->assertSame($app, $container->get(ApplicationInterface::class));
        $this->assertSame($app->config(), $container->get(ConfigurationInterface::class));
        $this->assertSame($app->environment(), $container->get(Environment::class));
    }

    public function testUnknownDependenciesAndCyclesAreRejected(): void
    {
        try {
            $this->bare()->withModules(OrphanModule::class)->build();
            $this->fail('An unknown dependency must be rejected.');
        } catch (ApplicationException $e) {
            $this->assertSame('Module Orphan depends on ' . StandaloneModule::class . ', which is not enabled. Add it to config/modules.php.', $e->getMessage());
        }

        $this->assertCount(2, $this->bare()->withModules(OrphanModule::class, StandaloneModule::class)->build()->modules());

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Module dependency cycle: Alpha → Beta → Alpha.');
        $this->bare()->withModules(AlphaModule::class, BetaModule::class)->build();
    }

    public function testProvidersCannotBuildTheContainer(): void
    {
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('must not build the container');

        $this->bare()->withProviders(GreedyProvider::class)->build();
    }

    public function testInvalidModuleListsAreRejected(): void
    {
        $base = $this->tempApp(['config/modules.php' => '<?php return [\stdClass::class];']);

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage("lists 'stdClass', which is not a Zana\\Core\\Module class");
        Application::configure($base)->build();
    }

    public function testInvalidProvidersAreRejected(): void
    {
        $this->expectException(ApplicationException::class);

        /** @phpstan-ignore argument.type (an invalid provider is under test) */
        $this->bare()->withProviders(\stdClass::class)->build();
    }

    // ── paths and termination ──────────────────────────────────────────────

    public function testPaths(): void
    {
        $app = Application::configure('C:\\apps\\bmi\\')->build();

        $this->assertSame('C:/apps/bmi', $app->basePath());
        $this->assertSame('C:/apps/bmi/var/cache/x.php', $app->path('var', '/cache/', 'x.php'));
        $this->assertSame('C:/apps/bmi', $app->path());
        $this->assertStringEndsWith('/Fixtures/app/modules/Patients/Configuration', (new PatientsModule())->path('Configuration'));
    }

    public function testTerminateDisposesTheContainer(): void
    {
        $app = $this->bare()->withProviders(FrameworkProvider::class)->build();
        $connection = $app->container()->get(Connection::class);

        $app->terminate();
        $app->terminate();

        $this->assertTrue($connection->closed);
        $this->assertTrue($app->container()->isDisposed());
    }

    private function fixture(): ApplicationBuilder
    {
        return Application::configure(__DIR__ . '/Fixtures/app')->withEnvironment('testing');
    }

    private function bare(): ApplicationBuilder
    {
        return Application::configure(sys_get_temp_dir() . '/zana-core-empty-' . getmypid());
    }

    /**
     * @param array<string, string> $files
     */
    private function tempApp(array $files): string
    {
        $base = sys_get_temp_dir() . '/zana-core-app-' . bin2hex(random_bytes(6));
        foreach ($files as $path => $contents) {
            @mkdir(dirname("{$base}/{$path}"), 0777, true);
            file_put_contents("{$base}/{$path}", $contents);
        }

        return $base;
    }
}
