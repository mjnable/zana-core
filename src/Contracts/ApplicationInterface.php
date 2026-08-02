<?php

declare(strict_types=1);

namespace Zana\Core\Contracts;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Configuration\Contracts\ConfigurationInterface;

/**
 * Application lifecycle interface.
 * 
 * Defines the boot/run/terminate lifecycle that all Zana applications follow.
 * The Application is the composition root — it wires together services,
 * configures providers, and manages the execution context.
 */
interface ApplicationInterface
{
    /**
     * Get the application's service container.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the application's configuration repository.
     *
     * @return ConfigurationInterface
     */
    public function getConfiguration(): ConfigurationInterface;

    /**
     * Get the application environment (development, testing, staging, production).
     *
     * @return string
     */
    public function environment(): string;

    /**
     * Check if the application is running in a specific environment.
     *
     * @param string ...$environments
     * @return bool
     */
    public function isEnvironment(string ...$environments): bool;

    /**
     * Determine if the application is in debug mode.
     *
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * Boot the application.
     * Registers all service providers and configuration.
     */
    public function boot(): void;

    /**
     * Run the application and return the response.
     * 
     * @return mixed
     */
    public function run(): mixed;

    /**
     * Terminate the application.
     * Performs cleanup and disposes scoped services.
     */
    public function terminate(): void;

    /**
     * Get the base path of the application.
     *
     * @param string $path Optional path to append
     * @return string
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the configuration path.
     *
     * @param string $path Optional path to append
     * @return string
     */
    public function configPath(string $path = ''): string;
}

