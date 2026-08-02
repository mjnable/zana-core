<?php

declare(strict_types=1);

namespace Zana\Core\Bootstrap;

/**
 * Minimal .env file loader.
 *
 * Reads a .env file and sets each variable via putenv() and $_ENV.
 * Supports:
 *   - KEY=VALUE pairs
 *   - Comments (lines starting with #)
 *   - Blank lines
 *   - Quoted values (single and double quotes)
 *   - Variable default values in comments (ignored)
 */
class LoadEnvironment
{
    /**
     * Load environment variables from a .env file.
     *
     * @param string $path Full path to the .env file
     * @throws \RuntimeException If the file does not exist
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return; // Silently skip if no .env file
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and blank lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Strip quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            // Only set if not already defined (allows OS env to override)
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

