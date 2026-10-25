<?php

declare(strict_types=1);

namespace Zana\Core;

use Closure;
use Psr\Log\LoggerInterface;
use Zana\Configuration\ConfigurationBuilder;
use Zana\Configuration\ConfigurationInterface;
use Zana\Configuration\ConfigValueProvider;
use Zana\Configuration\DotEnv;
use Zana\Container\ContainerCompiler;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Exception\StaleCompilationException;
use Zana\Container\ServiceCollection;
use Zana\Core\Internal\ApplicationHolder;
use Zana\Core\Internal\ModuleSorter;
use Zana\Core\Internal\OriginServiceCollection;

/**
 * Composes and boots an application (core specification §3).
 */
final class ApplicationBuilder
{
    private ?string $environment = null;

    /** @var list<ServiceProvider|class-string<ServiceProvider>> */
    private array $providers = [];

    /** @var list<Module|class-string<Module>> */
    private array $modules = [];

    /** @var list<Closure(ServiceCollectionInterface): mixed> */
    private array $services = [];

    private ?string $containerCache = null;

    /** @var array<string, mixed> */
    private array $configuration = [];

    /** @var array<string, class-string> Compiled container classes loaded in this process, by file */
    private static array $compiled = [];

    private readonly string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    }

    /**
     * Use this environment instead of APP_ENV.
     */
    public function withEnvironment(string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * @param ServiceProvider|class-string<ServiceProvider> ...$providers
     */
    public function withProviders(ServiceProvider|string ...$providers): self
    {
        array_push($this->providers, ...array_values($providers));

        return $this;
    }

    /**
     * Enable modules in addition to those listed in config/modules.php.
     *
     * @param Module|class-string<Module> ...$modules
     */
    public function withModules(Module|string ...$modules): self
    {
        array_push($this->modules, ...array_values($modules));

        return $this;
    }

    /**
     * Register application services after providers and modules.
     *
     * @param Closure(ServiceCollectionInterface): mixed $register
     */
    public function withServices(Closure $register): self
    {
        $this->services[] = $register;

        return $this;
    }

    /**
     * Cache the compiled container in this PHP file (see the core specification §3, step 6).
     */
    public function withContainerCache(string $file): self
    {
        $this->containerCache = $file;

        return $this;
    }

    /**
     * Override configuration values: a last layer, above environment variables.
     * Keys may be nested arrays or dotted, e.g. ['site.url' => 'http://localhost'].
     *
     * @param array<array-key, mixed> $overrides
     */
    public function withConfiguration(array $overrides): self
    {
        $this->configuration = ConfigurationBuilder::merge($this->configuration, self::expandDotted($overrides));

        return $this;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Run the boot lifecycle and return the booted application.
     *
     * @throws ApplicationException
     * @throws Deployment\UnsafeDeploymentException When a deployment check is fatal
     */
    public function build(): Application
    {
        $this->loadDotEnv();

        $environment = $this->detectEnvironment();
        $modules = ModuleSorter::sort($this->resolveModules());
        $config = $this->loadConfiguration($environment, $modules);
        $providers = array_map(self::instantiate(...), $this->providers);

        $services = new ServiceCollection([$environment->name]);
        $holder = new ApplicationHolder();

        foreach ([...$providers, ...$modules] as $provider) {
            $provider->register(new OriginServiceCollection($services, $provider->origin()));
        }
        foreach ($this->services as $register) {
            $register(new OriginServiceCollection($services, 'app'));
        }

        $services
            ->singleton(ApplicationInterface::class, static fn (): ApplicationInterface => $holder->get(), origin: 'zana/core')
            ->instance(ConfigurationInterface::class, $config, origin: 'zana/core')
            ->instance(Environment::class, $environment, origin: 'zana/core')
            ->addParameterProvider(new ConfigValueProvider($config));

        $container = $this->buildContainer($services, $environment);

        $application = new Application($this->basePath, $environment, $config, $container, $modules);
        $holder->set($application);

        foreach ([...$providers, ...$modules] as $provider) {
            $provider->boot($container);
        }

        $this->runDeploymentChecks($application, $container);

        return $application;
    }

    /**
     * .env, or .env.testing instead in the testing environment when it exists,
     * so a developer's .env never leaks into the test run.
     */
    private function loadDotEnv(): void
    {
        $environment = $this->environment ?? self::env('APP_ENV');
        $testing = $this->basePath . '/.env.testing';

        DotEnv::load($environment === 'testing' && is_file($testing) ? $testing : $this->basePath . '/.env');
    }

    /**
     * @throws Deployment\UnsafeDeploymentException
     */
    private function runDeploymentChecks(Application $application, ContainerInterface $container): void
    {
        if (!$application->config()->bool('app.deployment_checks', true)) {
            return;
        }

        $checks = [new Deployment\BuiltInChecks($this->containerCache)];
        foreach ($container->tagged(Deployment\DeploymentCheck::TAG) as $check) {
            if (!$check instanceof Deployment\DeploymentCheck) {
                throw new ApplicationException(sprintf('Services tagged "%s" must implement %s; %s does not.', Deployment\DeploymentCheck::TAG, Deployment\DeploymentCheck::class, $check::class));
            }
            $checks[] = $check;
        }

        $problems = [];
        foreach ($checks as $check) {
            $result = $check->check($application);
            if ($result->status === Deployment\CheckStatus::Fatal) {
                $problems[] = $result->message;
            } elseif ($result->status === Deployment\CheckStatus::Warning && $container->has(LoggerInterface::class)) {
                $logger = $container->get(LoggerInterface::class);
                if ($logger instanceof LoggerInterface) {
                    $logger->warning('Deployment check: ' . $result->message);
                }
            }
        }

        if ($problems !== []) {
            throw Deployment\UnsafeDeploymentException::because($problems);
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function expandDotted(array $values): array
    {
        $expanded = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                $value = self::expandDotted($value);
            }
            foreach (array_reverse(explode('.', (string) $key)) as $segment) {
                $value = [$segment => $value];
            }
            /** @var array<string, mixed> $value */
            $expanded = ConfigurationBuilder::merge($expanded, $value);
        }

        return $expanded;
    }

    private function detectEnvironment(): Environment
    {
        $name = $this->environment ?? self::env('APP_ENV') ?? 'production';
        $debug = self::env('APP_DEBUG');

        return new Environment(
            $name,
            $debug === null ? $name !== 'production' : in_array(strtolower($debug), ['1', 'true', 'yes', 'on'], true),
        );
    }

    /**
     * Builder modules plus config/modules.php, de-duplicated, in declared order.
     *
     * @return list<Module>
     */
    private function resolveModules(): array
    {
        $declared = $this->modules;

        $file = $this->basePath . '/config/modules.php';
        if (is_file($file)) {
            $listed = (static fn (string $path): mixed => require $path)($file);
            if (!is_array($listed) || !array_is_list($listed)) {
                throw new ApplicationException("{$file} must return a list of module class names.");
            }
            foreach ($listed as $class) {
                if (!is_string($class) || !is_subclass_of($class, Module::class)) {
                    throw new ApplicationException(sprintf('%s lists %s, which is not a %s class.', $file, var_export($class, true), Module::class));
                }
                $declared[] = $class;
            }
        }

        $modules = [];
        foreach ($declared as $module) {
            $instance = self::instantiate($module);
            $modules[$instance::class] ??= $instance;
        }

        return array_values($modules);
    }

    /**
     * @param list<Module> $modules
     */
    private function loadConfiguration(Environment $environment, array $modules): ConfigurationInterface
    {
        $builder = new ConfigurationBuilder();

        foreach ($modules as $module) {
            $key = strtolower($module->name());
            foreach (glob($module->path('Configuration', '*.php')) ?: [] as $file) {
                $builder->addFile($file, $key . '.' . pathinfo($file, PATHINFO_FILENAME));
            }
        }

        return $builder
            ->addDirectory($this->basePath . '/config', except: ['modules'])
            ->addDirectory($this->basePath . '/config/' . $environment->name)
            ->addEnvironment()
            ->addArray($this->configuration)
            ->build();
    }

    private function buildContainer(ServiceCollection $services, Environment $environment): ContainerInterface
    {
        if ($this->containerCache === null) {
            return $services->build();
        }

        $file = $this->containerCache;
        $remembered = isset(self::$compiled[$file]);
        if ($remembered || is_file($file)) {
            try {
                return $services->build(compiled: self::loadCompiled($file));
            } catch (StaleCompilationException $e) {
                if ($remembered) {
                    // Another process may have rewritten the file since this one loaded it
                    try {
                        return $services->build(compiled: self::$compiled[$file] = self::classDeclaredIn($file));
                    } catch (StaleCompilationException $e) {
                    }
                }
                if ($environment->isProduction()) {
                    throw $e;
                }
            }
        }

        $class = 'Zana\\CompiledContainer\\Container' . bin2hex(random_bytes(8));
        $source = (new ContainerCompiler())->compile($services, $class);
        self::writeAtomically($file, $source . "\nreturn \\{$class}::class;\n");
        unset(self::$compiled[$file]);

        return $services->build(compiled: self::loadCompiled($file));
    }

    /**
     * Include a compiled container file and return the class it declares.
     *
     * The file returns its class name, so a load costs one include (served by
     * OPcache) rather than reading the file; the name is then remembered.
     *
     * @return class-string
     */
    private static function loadCompiled(string $file): string
    {
        if (isset(self::$compiled[$file])) {
            return self::$compiled[$file];
        }

        $name = require $file;
        if (is_string($name) && class_exists($name, false)) {
            return self::$compiled[$file] = $name;
        }

        // A file written by an earlier version, without the return statement
        return self::$compiled[$file] = self::classDeclaredIn($file);
    }

    /**
     * Find the class a compiled container file declares by reading it, and
     * include the file unless the class is already declared.
     *
     * @return class-string
     */
    private static function classDeclaredIn(string $file): string
    {
        $source = (string) file_get_contents($file);
        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^final class (\w+) extends/m', $source, $class) !== 1
        ) {
            throw new ApplicationException("{$file} is not a compiled container.");
        }

        $name = $namespace[1] . '\\' . $class[1];
        if (!class_exists($name, false)) {
            require $file;
        }

        if (!class_exists($name, false)) {
            throw new ApplicationException("{$file} does not declare {$name}.");
        }

        return $name;
    }

    private static function writeAtomically(string $file, string $contents): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new ApplicationException("Cannot create the directory for the container cache: {$directory}.");
        }

        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $contents) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new ApplicationException("Cannot write the container cache: {$file}.");
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * @template T of ServiceProvider
     * @param T|class-string<T> $provider
     * @return T
     */
    private static function instantiate(ServiceProvider|string $provider): ServiceProvider
    {
        if ($provider instanceof ServiceProvider) {
            return $provider;
        }

        if (!is_subclass_of($provider, ServiceProvider::class)) {
            throw new ApplicationException("{$provider} is not a " . ServiceProvider::class . '.');
        }

        return new $provider();
    }

    private static function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
