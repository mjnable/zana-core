<?php

declare(strict_types=1);

namespace Zana\Core;

/**
 * A self-contained business capability (ADR-001): a service provider with a
 * name, declared dependencies on other modules, and a folder of its own.
 */
abstract class Module extends ServiceProvider
{
    abstract public function name(): string;

    /**
     * Modules this one needs, which must also be enabled.
     *
     * @return list<class-string<Module>>
     */
    public function dependsOn(): array
    {
        return [];
    }

    /**
     * A path inside the module's directory (the directory of the module class).
     */
    public function path(string ...$segments): string
    {
        $directory = dirname((string) (new \ReflectionClass($this))->getFileName());

        return implode('/', [str_replace('\\', '/', $directory), ...$segments]);
    }

    public function origin(): string
    {
        return 'module:' . $this->name();
    }
}
