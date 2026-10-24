<?php

declare(strict_types=1);

namespace Zana\Core\Internal;

use Closure;
use Zana\Container\BuildReport;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ParameterValueProviderInterface;
use Zana\Container\Contracts\ResolutionInterceptorInterface;
use Zana\Container\Contracts\ScopeObserverInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\ServiceLifetime;
use Zana\Core\ApplicationException;

/**
 * The collection a provider registers into: it sets the provider's origin on
 * every registration that has none, so diagnostics name the module or package.
 * Providers must not build the container themselves.
 *
 * @internal
 */
final class OriginServiceCollection implements ServiceCollectionInterface
{
    public function __construct(
        private readonly ServiceCollectionInterface $inner,
        private readonly string $origin,
    ) {
    }

    public function singleton(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->singleton($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function scoped(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->scoped($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function transient(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->transient($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function trySingleton(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->trySingleton($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function tryScoped(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->tryScoped($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function tryTransient(string $service, string|Closure|null $implementation = null, ?string $key = null, bool $lazy = false, ?string $origin = null): static
    {
        $this->inner->tryTransient($service, $implementation, $key, $lazy, $origin ?? $this->origin);

        return $this;
    }

    public function instance(string $service, object $instance, ?string $key = null, ?string $origin = null): static
    {
        $this->inner->instance($service, $instance, $key, $origin ?? $this->origin);

        return $this;
    }

    public function replace(string $service, string|Closure $implementation, ?string $key = null, ?ServiceLifetime $lifetime = null, ?string $origin = null): static
    {
        $this->inner->replace($service, $implementation, $key, $lifetime, $origin ?? $this->origin);

        return $this;
    }

    public function remove(string $service, ?string $key = null): static
    {
        $this->inner->remove($service, $key);

        return $this;
    }

    public function alias(string $alias, string $target): static
    {
        $this->inner->alias($alias, $target);

        return $this;
    }

    public function tag(string $tag, string ...$services): static
    {
        $this->inner->tag($tag, ...$services);

        return $this;
    }

    public function decorate(string $service, string|Closure $decorator): static
    {
        $this->inner->decorate($service, $decorator);

        return $this;
    }

    public function when(bool|Closure $condition, Closure $register): static
    {
        $this->inner->when($condition, fn (): mixed => $register($this));

        return $this;
    }

    public function profile(string $profile, Closure $register): static
    {
        $this->inner->profile($profile, fn (): mixed => $register($this));

        return $this;
    }

    public function activeProfiles(): array
    {
        return $this->inner->activeProfiles();
    }

    public function addAttributed(string ...$classes): static
    {
        $this->inner->addAttributed(...$classes);

        return $this;
    }

    public function scan(string $directory, string $namespace, ?string $origin = null): static
    {
        $this->inner->scan($directory, $namespace, $origin ?? $this->origin);

        return $this;
    }

    public function addParameterProvider(ParameterValueProviderInterface $provider): static
    {
        $this->inner->addParameterProvider($provider);

        return $this;
    }

    public function addInterceptor(ResolutionInterceptorInterface $interceptor): static
    {
        $this->inner->addInterceptor($interceptor);

        return $this;
    }

    public function addScopeObserver(ScopeObserverInterface $observer): static
    {
        $this->inner->addScopeObserver($observer);

        return $this;
    }

    public function provide(string $capability, ?string $origin = null): static
    {
        $this->inner->provide($capability, $origin ?? $this->origin);

        return $this;
    }

    public function requires(string $capability, ?string $origin = null): static
    {
        $this->inner->requires($capability, $origin ?? $this->origin);

        return $this;
    }

    public function whenCapability(string $capability, Closure $register): static
    {
        $this->inner->whenCapability($capability, fn (): mixed => $register($this));

        return $this;
    }

    public function has(string $service, ?string $key = null): bool
    {
        return $this->inner->has($service, $key);
    }

    public function descriptors(): array
    {
        return $this->inner->descriptors();
    }

    public function analyze(): BuildReport
    {
        throw new ApplicationException("{$this->origin} must not analyze the service collection; the application does.");
    }

    public function build(?string $compiled = null): ContainerInterface
    {
        throw new ApplicationException("{$this->origin} must not build the container; the application does.");
    }
}
