<?php

declare(strict_types=1);

namespace Zana\Core\Lifecycle;

use Zana\Core\Contracts\ApplicationInterface;

/**
 * Manages the application lifecycle events.
 * 
 * Provides hooks for:
 * - Before boot
 * - After boot
 * - Before run
 * - After run
 * - Terminate
 */
class LifecycleManager
{
    /** @var array<string, list<callable>> */
    private array $hooks = [];

    /**
     * Register a hook for a lifecycle event.
     *
     * @param string $event The lifecycle event name
     * @param callable $callback The callback to execute
     */
    public function hook(string $event, callable $callback): void
    {
        $this->hooks[$event][] = $callback;
    }

    /**
     * Execute all hooks for a given event.
     *
     * @param string $event The lifecycle event name
     * @param ApplicationInterface $app The application instance
     */
    public function execute(string $event, ApplicationInterface $app): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }

        foreach ($this->hooks[$event] as $callback) {
            $callback($app);
        }
    }

    /**
     * Clear all registered hooks.
     */
    public function clear(): void
    {
        $this->hooks = [];
    }
}

