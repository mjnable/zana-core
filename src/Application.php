<?php

declare(strict_types=1);

namespace Zana\Core;

use Zana\Container\ServiceCollection;
use Zana\Container\ServiceProvider;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Configuration\ConfigurationRepository;
use Zana\Configuration\Contracts\ConfigurationInterface;
use Zana\Core\Contracts\ApplicationInterface;
use Zana\Core\Exceptions\ApplicationException;

/**
 * Zana Application — the composition root and lifecycle manager.
 * 
 * Manages the boot/run/terminate lifecycle:
 * - Boot: Create the container, load configuration, register providers
 * - Run: Execute the application and return a response
 * - Terminate: Cleanup resources, dispose scoped services
 */
class Application implements ApplicationInterface
{
    private ContainerInterface $container;

    private ConfigurationInterface $configuration;

    private ServiceCollection $serviceCollection;

    /** @var array<int, object> Registered service providers */
    private array $providers = [];

    private bool $booted = false;

    private bool $running = false;

    private string $environment;

    private bool $debug;

    private string $basePath;

    /**
     * @param string $basePath The root directory of the application
     * @param string $environment The application environment (development, testing, staging, production)
     * @param bool $debug Whether debug mode is enabled
     */
    public function __construct(
        string $basePath,
        string $environment = 'development',
        bool $debug = true
    ) {
        $this->basePath = rtrim($basePath, '/\\');
        $this->environment = $environment;
        $this->debug = $debug;
        $this->configuration = new ConfigurationRepository();
        $this->serviceCollection = new ServiceCollection();

        // Register self in the container
        $this->serviceCollection->instance(ApplicationInterface::class, $this);
        $this->serviceCollection->instance(self::class, $this);
        $this->serviceCollection->instance(ConfigurationInterface::class, $this->configuration);
    }

    /**
     * Get the service container.
     */
    public function getContainer(): ContainerInterface
    {
        if (!isset($this->container)) {
            throw new ApplicationException(
                'Cannot access container before application has booted.'
            );
        }
        return $this->container;
    }

    /**
     * Get the configuration repository.
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * Get the service collection for registering services.
     * Only available before boot().
     */
    public function getServiceCollection(): ServiceCollection
    {
        if ($this->booted) {
            throw new ApplicationException(
                'Cannot modify service collection after application has booted.'
            );
        }
        return $this->serviceCollection;
    }

    /**
     * Get the current environment.
     */
    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * Check if running in specified environments.
     */
    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments, true);
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Register a service provider.
     *
     * @param object $provider The service provider instance
     */
    public function register(object $provider): void
    {
        if ($this->booted) {
            throw new ApplicationException(
                'Cannot register providers after application has booted.'
            );
        }

        $this->providers[] = $provider;

        // Call register on the provider if it has the method
        if (method_exists($provider, 'register')) {
            $provider->register($this->serviceCollection);
        }
    }

    /**
     * Register an array of service providers.
     *
     * @param array<int, object> $providers
     */
    public function registerProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Load configuration from PHP files in the config directory.
     *
     * @param string|null $configDir Optional custom config directory
     */
    public function loadConfiguration(?string $configDir = null): void
    {
        $configDir ??= $this->configPath();

        if (!is_dir($configDir)) {
            return;
        }

        $files = glob($configDir . '/*.php');
        if ($files === false) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $data = require $file;
            if (is_array($data)) {
                $basename = pathinfo($file, PATHINFO_FILENAME);
                $this->configuration->set($basename, $data);
            }
        }
    }

    /**
     * Boot the application.
     * Builds the container from the service collection and calls boot on providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Register the container itself so it can be injected (e.g. Mediator)
        $this->serviceCollection->singleton(
            ContainerInterface::class,
            function () {
                return $this->container;
            }
        );

        // Build the container
        $provider = $this->serviceCollection->build();

        if (!$provider instanceof ServiceProvider) {
            throw new ApplicationException(
                'Service collection did not return a ServiceProvider instance.'
            );
        }

        $this->container = $provider;

        // Call boot on all providers
        foreach ($this->providers as $registered) {
            if (method_exists($registered, 'boot')) {
                $registered->boot();
            }
        }

        $this->booted = true;
    }

    /**
     * Run the application.
     * Boots if not already booted, then delegates to the kernel.
     *
     * @return mixed
     */
    public function run(): mixed
    {
        if (!$this->booted) {
            $this->boot();
        }

        if ($this->running) {
            throw new ApplicationException('Application is already running.');
        }

        $this->running = true;

        try {
            // Check if an HTTP kernel is registered
            if ($this->container->has(\Zana\Http\Kernel\HttpKernelInterface::class)) {
                $kernel = $this->container->get(\Zana\Http\Kernel\HttpKernelInterface::class);
                return $kernel->handle();
            }

            // Check if a console kernel is registered
            if ($this->container->has(\Zana\Console\Contracts\ConsoleKernelInterface::class)) {
                $kernel = $this->container->get(\Zana\Console\Contracts\ConsoleKernelInterface::class);
                return $kernel->handle();
            }

            // No kernel registered — just return
            return null;
        } finally {
            $this->running = false;
        }
    }

    /**
     * Terminate the application.
     * Disposes the root scope and releases resources.
     */
    public function terminate(): void
    {
        if (!$this->booted) {
            return;
        }

        // Terminate HTTP kernel if available
        if (isset($this->container) && $this->container->has(\Zana\Http\Kernel\HttpKernelInterface::class)) {
            try {
                $kernel = $this->container->get(\Zana\Http\Kernel\HttpKernelInterface::class);
                if (method_exists($kernel, 'terminate')) {
                    $kernel->terminate();
                }
            } catch (\Throwable) {
                // Silently handle termination errors
            }
        }

        $this->booted = false;
        $this->running = false;
    }

    /**
     * Get the base path.
     */
    public function basePath(string $path = ''): string
    {
        return $path !== ''
            ? $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\')
            : $this->basePath;
    }

    /**
     * Get the config directory path.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }

    /**
     * Check if the application has booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Check if the application is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}

