<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

use Zana\Core\ApplicationInterface;

/**
 * The checks every application runs (core specification, 2.1 additions).
 * They are plain objects, not container services, so they never change the
 * compiled container.
 *
 * @internal
 */
final readonly class BuiltInChecks implements DeploymentCheck
{
    private const array LOCAL_SUFFIXES = ['.localhost', '.test', '.local', '.internal', '.example'];
    private const array PLACEHOLDER_PREFIXES = ['change-me', 'changeme', 'your-'];

    public function __construct(private ?string $containerCache = null, private ?string $configurationCache = null)
    {
    }

    public function check(ApplicationInterface $application): CheckResult
    {
        $problems = [];
        $warnings = [];
        $environment = $application->environment();
        $config = $application->config();

        if ($environment->debug && $environment->isProduction()) {
            $problems[] = 'Debug mode is on in production. Set APP_DEBUG=false.';
        }

        $url = $config->has('app.url') ? $config->string('app.url') : '';
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($environment->debug && $url !== '' && !self::isLocalHost($host)) {
            $problems[] = "Debug mode is on for the public URL {$url}: errors would show code and paths to visitors. Set APP_ENV=production and APP_DEBUG=false.";
        }

        if ($environment->isProduction()) {
            /** @var list<string> $keys */
            $keys = $config->array('app.secret_keys', ['security.secret']);
            foreach ($keys as $key) {
                if ($config->has($key) && self::isPlaceholder($config->get($key))) {
                    $problems[] = "{$key} is empty or a placeholder. Generate one, e.g. php -r \"echo bin2hex(random_bytes(32));\"";
                }
            }
        }

        foreach (array_filter(['container' => $this->containerCache, 'configuration' => $this->configurationCache]) as $kind => $file) {
            if (is_file($file)) {
                continue;
            }
            $directory = dirname($file);
            $existing = $directory;
            while (!is_dir($existing) && dirname($existing) !== $existing) {
                $existing = dirname($existing);
            }
            if (!is_writable($existing)) {
                $warnings[] = $kind === 'container'
                    ? "The container cache cannot be written to {$directory}; every request compiles the container."
                    : "The configuration cache cannot be written to {$directory}; every request reads the configuration files.";
            }
        }

        return match (true) {
            $problems !== [] => CheckResult::fatal(implode("\n", $problems)),
            $warnings !== [] => CheckResult::warning(implode("\n", $warnings)),
            default => CheckResult::ok(),
        };
    }

    /**
     * Local hosts: localhost, loopback and private addresses, and reserved
     * development suffixes (.localhost, .test, .local, .internal, .example).
     */
    public static function isLocalHost(string $host): bool
    {
        $host = trim(strtolower($host), '[]');
        if ($host === '' || $host === 'localhost' || $host === '::1') {
            return true;
        }
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return str_starts_with($host, '127.')
                || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    private static function isPlaceholder(mixed $value): bool
    {
        if (!is_string($value)) {
            return $value === null;
        }
        $value = strtolower(trim($value));
        if ($value === '' || $value === 'secret') {
            return true;
        }
        foreach (self::PLACEHOLDER_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
