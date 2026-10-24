<?php

declare(strict_types=1);

namespace Zana\Core;

use Zana\Container\Contracts\ServiceResolverInterface;

/**
 * Correlation ids tie together the log lines of one unit of work (an HTTP
 * request, a console command, a queue job), across processes when the id is
 * passed along.
 *
 * Kernels put the id in the scope metadata (ScopeKeys::CORRELATION_ID) and,
 * when zana/logging is installed, in its LogContext so every log record
 * carries it. zana/core does not depend on zana/logging: the LogContext is
 * looked up by service id and used only if it is registered.
 */
final class Correlation
{
    /** The service id of zana/logging's LogContext. */
    public const string LOG_CONTEXT = 'Zana\\Logging\\LogContext';

    private function __construct()
    {
    }

    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Whether an incoming id (a header, an environment variable) is safe to reuse:
     * 1 to 128 letters, digits, dots, underscores or hyphens.
     */
    public static function isValid(?string $id): bool
    {
        return $id !== null && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $id) === 1;
    }

    /**
     * The incoming id when valid, otherwise a new one.
     */
    public static function from(?string $incoming): string
    {
        return self::isValid($incoming) ? (string) $incoming : self::generate();
    }

    /**
     * Set the id in the LogContext of $scope, if there is one.
     *
     * @return \Closure(): void Restores the previous value; call it when the unit of work ends
     */
    public static function applyToLogContext(ServiceResolverInterface $scope, string $id): \Closure
    {
        $context = $scope->has(self::LOG_CONTEXT) ? $scope->get(self::LOG_CONTEXT) : null;
        if (!is_object($context) || !method_exists($context, 'set') || !method_exists($context, 'remove') || !method_exists($context, 'all')) {
            return static function (): void {
            };
        }

        $all = $context->all();
        $previous = is_array($all) ? ($all[ScopeKeys::CORRELATION_ID] ?? null) : null;
        $context->set(ScopeKeys::CORRELATION_ID, $id);

        return static function () use ($context, $previous): void {
            $previous === null
                ? $context->remove(ScopeKeys::CORRELATION_ID)
                : $context->set(ScopeKeys::CORRELATION_ID, $previous);
        };
    }
}
