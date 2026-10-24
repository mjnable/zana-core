# Zana Core

Compose an application from service providers and business modules, then boot
it. Requires PHP 8.5 or later.

```
composer require mj-zana/core
```

## Usage

```php
use Zana\Core\Application;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders(LoggingServiceProvider::class, HttpServiceProvider::class)
    ->withServices(fn ($services) => $services->singleton(Clock::class))
    ->withContainerCache(dirname(__DIR__) . '/var/cache/container.php')   // optional
    ->build();

$app->container()->get(Clock::class);
$app->config()->get('app.name');
$app->environment()->isProduction();
$app->terminate();
```

## Modules

Modules are listed explicitly in `config/modules.php` (ADR-009):

```php
return [
    Modules\Patients\PatientsModule::class,
    Modules\Bmi\BmiModule::class,
];
```

```php
final class BmiModule extends Module
{
    public function name(): string { return 'Bmi'; }

    public function dependsOn(): array { return [PatientsModule::class]; }

    public function register(ServiceCollectionInterface $services): void
    {
        $services->scoped(MeasurementRepository::class, PdoMeasurementRepository::class);
    }

    public function boot(ContainerInterface $container): void
    {
        // add routes, listeners, commands...
    }
}
```

Modules are sorted so dependencies come first. Unknown dependencies and cycles
fail at startup. Every registration records its origin (`module:Bmi`), which
appears in container errors and the service graph.

## Boot lifecycle

1. Load `.env`, without overriding real variables.
2. Detect the environment: `APP_ENV`, defaulting to production. `APP_DEBUG` sets debug.
3. Build the configuration. Later layers win:
   1. each module's `Configuration/*.php`, under the module's name;
   2. `config/*.php`;
   3. `config/<environment>/*.php`;
   4. `APP_*` environment variables.
4. Register services, in this order: providers, modules (in dependency order),
   then `withServices()` callbacks. The environment name is the active
   container profile.
5. Build the container (compiled and cached when configured).
6. Boot providers and modules, in the same order.

## License

MIT — see [LICENSE](LICENSE).
