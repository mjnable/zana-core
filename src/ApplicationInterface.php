<?php

declare(strict_types=1);

namespace Zana\Core;

use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ContainerInterface;

/**
 * A booted Zana application.
 */
interface ApplicationInterface
{
    public function basePath(): string;

    /**
     * A path below the base path, joined with "/".
     */
    public function path(string ...$segments): string;

    public function environment(): Environment;

    public function config(): ConfigurationInterface;

    public function container(): ContainerInterface;

    /**
     * Enabled modules, in dependency order.
     *
     * @return list<Module>
     */
    public function modules(): array;

    /**
     * Dispose the container and the services it owns. Idempotent.
     */
    public function terminate(): void;
}
