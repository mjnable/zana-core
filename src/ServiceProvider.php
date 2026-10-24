<?php

declare(strict_types=1);

namespace Zana\Core;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;

/**
 * Registers a package's or module's services, then initialises them once the
 * container is built.
 */
abstract class ServiceProvider
{
    /**
     * Register services. Must not resolve anything: the container does not exist yet.
     */
    abstract public function register(ServiceCollectionInterface $services): void;

    /**
     * Initialise registered services: add routes, listeners, commands and so on.
     */
    public function boot(ContainerInterface $container): void
    {
    }

    /**
     * The origin recorded on every registration this provider makes.
     */
    public function origin(): string
    {
        return 'provider:' . static::class;
    }
}
