<?php

declare(strict_types=1);

namespace Zana\Core;

/**
 * The console script of the running command, for hints that tell people and
 * agents what to run (FE-54, ADR-026). The console kernel records it when it
 * reads the real command line; otherwise (a web request, a test, a deployment
 * check at boot) it is unknown, and hints name the command only:
 *
 *     ConsoleScript::command('migrate')   // "`php bin/zana migrate`", or "the console command `migrate`"
 */
final class ConsoleScript
{
    private static ?string $script = null;

    /**
     * Record the script that started the console, e.g. $_SERVER['argv'][0]; made relative to
     * $basePath when it lies inside it. Null forgets it.
     */
    public static function record(?string $script, ?string $basePath = null): void
    {
        if ($script === null || trim($script) === '') {
            self::$script = null;

            return;
        }
        $script = str_replace('\\', '/', $script);
        $base = $basePath === null ? null : rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        if ($base !== null && str_starts_with(strtolower($script), strtolower($base))) {
            $script = substr($script, strlen($base));
        }
        self::$script = $script;
    }

    /**
     * The recorded script, e.g. "bin/zana", or null.
     */
    public static function current(): ?string
    {
        return self::$script;
    }

    /**
     * How to run a console command, for a message: "`php bin/zana migrate`" when the script is known,
     * "the console command `migrate`" otherwise.
     */
    public static function command(string $command): string
    {
        return self::$script === null ? "the console command `{$command}`" : '`php ' . self::$script . " {$command}`";
    }
}
