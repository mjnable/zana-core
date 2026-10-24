<?php

declare(strict_types=1);

namespace Zana\Core;

/**
 * The environment the application runs in, e.g. "production", "development" or "testing".
 */
final readonly class Environment
{
    public function __construct(
        public string $name,
        public bool $debug,
    ) {
    }

    public function is(string $name): bool
    {
        return $this->name === $name;
    }

    public function isProduction(): bool
    {
        return $this->name === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->name === 'development';
    }

    public function isTesting(): bool
    {
        return $this->name === 'testing';
    }
}
