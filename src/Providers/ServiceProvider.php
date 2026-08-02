<?php

declare(strict_types=1);

namespace Zana\Core\Providers;

use Zana\Container\ServiceCollection;

/**
 * Base service provider for Zana packages and application modules.
 * 
 * All service providers extend this base class, which provides:
 * - A `register()` method for adding services to the container
 * - A `boot()` method for running code after all providers are registered
 * - Helper methods for common registration patterns
 * 
 * Inspired by the .NET Core/ASP.NET Core service provider pattern.
 */
abstract class ServiceProvider
{
    /**
     * The service collection for registering services.
     */
    protected ?ServiceCollection $services = null;

    /**
     * Register services with the service collection.
     *
     * Override this method to add services to the container.
     * Called during the application boot sequence before boot().
     *
     * @param ServiceCollection $services The service collection to register with
     */
    abstract public function register(ServiceCollection $services): void;

    /**
     * Boot the service provider.
     *
     * Override this method to run code after all providers have been registered.
     * All services are available in the container at this point.
     * 
     * Use this for:
     * - Registering event listeners
     * - Configuring middleware
     * - Setting up routes
     * - Initializing services that depend on other registered services
     */
    public function boot(): void
    {
        // Override in child classes as needed
    }

    /**
     * Check if the application is running in a development environment.
     * Available during boot() if the Application is registered.
     *
     * @return bool
     */
    protected function isDevelopment(): bool
    {
        if ($this->services !== null) {
            $app = $this->getApplication();
            if ($app !== null) {
                return $app->environment() === 'development';
            }
        }
        return true; // Default to development
    }

    /**
     * Check if the application is running in a production environment.
     *
     * @return bool
     */
    protected function isProduction(): bool
    {
        return !$this->isDevelopment();
    }

    /**
     * Get the application instance, if available.
     *
     * @return object|null
     */
    protected function getApplication(): ?object
    {
        try {
            return $this->services?->build()?->get(\Zana\Core\Contracts\ApplicationInterface::class);
        } catch (\Throwable) {
            return null;
        }
    }
}

