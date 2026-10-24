<?php

declare(strict_types=1);

namespace Zana\Core\Tests\Fixtures;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\Disposable;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Core\ServiceProvider;

final class Clock
{
}

final class Connection implements Disposable
{
    public bool $closed = false;

    public function dispose(): void
    {
        $this->closed = true;
    }
}

final class FrameworkProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        Journal::$entries[] = 'register:FrameworkProvider';
        $services->singleton(Clock::class)->singleton(Connection::class);
    }

    public function boot(ContainerInterface $container): void
    {
        Journal::$entries[] = 'boot:FrameworkProvider';
    }

    public function origin(): string
    {
        return 'package:fixture/framework';
    }
}

final class DefaultOriginProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services->transient('default-origin', static fn (): Clock => new Clock());
    }
}

final class GreedyProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services->build();
    }
}

final class AlphaModule extends Module
{
    public function name(): string
    {
        return 'Alpha';
    }

    public function dependsOn(): array
    {
        return [BetaModule::class];
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}

final class BetaModule extends Module
{
    public function name(): string
    {
        return 'Beta';
    }

    public function dependsOn(): array
    {
        return [AlphaModule::class];
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}

final class OrphanModule extends Module
{
    public function name(): string
    {
        return 'Orphan';
    }

    public function dependsOn(): array
    {
        return [StandaloneModule::class];
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}

final class StandaloneModule extends Module
{
    public function name(): string
    {
        return 'Standalone';
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }
}

final class ProfiledProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services->profile('testing', static fn (ServiceCollectionInterface $s) => $s->singleton('testing-only', Clock::class));
    }
}
