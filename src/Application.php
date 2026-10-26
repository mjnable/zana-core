<?php

declare(strict_types=1);

namespace Zana\Core;

use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ContainerInterface;

/**
 * A booted application. Create one with Application::configure(...)->build().
 */
final class Application implements ApplicationInterface
{
    /**
     * @param list<Module> $modules
     * @param (\Closure(): string)|null $compileContainer Rewrites the container cache (ApplicationBuilder::compileContainerCache())
     */
    public function __construct(
        private readonly string $basePath,
        private readonly Environment $environment,
        private readonly ConfigurationInterface $config,
        private readonly ContainerInterface $container,
        private readonly array $modules,
        private readonly ?string $containerCache = null,
        private readonly ?\Closure $compileContainer = null,
    ) {
    }

    /**
     * The compiled container cache file, when the application uses one.
     */
    public function containerCache(): ?string
    {
        return $this->containerCache;
    }

    /**
     * Recompile the container cache from the current registrations.
     *
     * @return string|null The cache file, or null without a cache
     */
    public function compileContainerCache(): ?string
    {
        return $this->compileContainer === null || $this->containerCache === null ? null : ($this->compileContainer)();
    }

    public static function configure(string $basePath): ApplicationBuilder
    {
        return new ApplicationBuilder($basePath);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function path(string ...$segments): string
    {
        $segments = array_map(static fn (string $s): string => trim(str_replace('\\', '/', $s), '/'), $segments);

        return implode('/', [$this->basePath, ...array_filter($segments, static fn (string $s): bool => $s !== '')]);
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function config(): ConfigurationInterface
    {
        return $this->config;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function modules(): array
    {
        return $this->modules;
    }

    public function terminate(): void
    {
        $this->container->dispose();
    }
}
