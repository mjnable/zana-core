<?php

declare(strict_types=1);

namespace Zana\Core\Internal;

use Zana\Configuration\ConfigurationBuilder;

/**
 * Signs the PHP files the builder writes and includes, the compiled container
 * and the configuration cache (2.35). Someone who can write to var/cache
 * could otherwise put code in them, which the next request runs.
 *
 * The signature is the file's second line, an HMAC-SHA256 of the rest and of
 * the kind of file, so one cannot stand for the other. The key is derived
 * from security.secret as .env, APP_* variables and withConfiguration() give
 * it: the layers read before the configuration cache, which cannot hold the
 * key that checks it.
 *
 * @internal
 */
final class CompiledFile
{
    public const string SIGNED = 'signed';
    public const string UNSIGNED = 'unsigned';
    public const string FORGED = 'forged';

    private const string PREFIX = "<?php\n// zana-signature: ";

    /**
     * The key, or null when no layer above the configuration cache sets security.secret.
     *
     * @param array<array-key, mixed> $overrides The values of withConfiguration()
     */
    public static function key(array $overrides): ?string
    {
        $secret = (new ConfigurationBuilder())->addEnvironment()->addArray($overrides)->build()->get('security.secret');
        if (!is_string($secret) && !is_int($secret)) {
            return null;
        }
        $secret = trim((string) $secret);

        return $secret === '' ? null : hash_hmac('sha256', 'zana.core.compiled', $secret);
    }

    /**
     * Add the signature to a PHP file's source, which starts with "<?php" and
     * a line break (CRLF in a checkout on Windows).
     */
    public static function sign(string $source, string $kind, string $key): string
    {
        if (preg_match('/\A<\?php\r?\n/', $source, $open) !== 1) {
            throw new \LogicException('A compiled file starts with "<?php" and a line break.');
        }
        $body = substr($source, strlen($open[0]));

        return self::PREFIX . self::mac($body, $kind, $key) . "\n" . $body;
    }

    /**
     * Whether the file carries this key's signature (SIGNED), none (UNSIGNED:
     * written without a key, or by a version before 2.35), or another one
     * (FORGED: changed, or signed with another secret).
     *
     * @return self::SIGNED|self::UNSIGNED|self::FORGED
     */
    public static function check(string $file, string $kind, string $key): string
    {
        $source = @file_get_contents($file);

        return self::verify(is_string($source) ? $source : '', $kind, $key);
    }

    /**
     * check() for a file's contents, already read.
     *
     * @return self::SIGNED|self::UNSIGNED|self::FORGED
     */
    public static function verify(string $source, string $kind, string $key): string
    {
        if (!str_starts_with($source, self::PREFIX)) {
            return self::UNSIGNED;
        }
        $end = strpos($source, "\n", strlen(self::PREFIX));
        if ($end === false) {
            return self::FORGED;
        }
        $mac = substr($source, strlen(self::PREFIX), $end - strlen(self::PREFIX));

        return hash_equals(self::mac(substr($source, $end + 1), $kind, $key), $mac) ? self::SIGNED : self::FORGED;
    }

    /**
     * Run a file's contents, once verified, and return what the file returns:
     * the bytes checked are the bytes that run.
     */
    public static function evaluate(string $source): mixed
    {
        return eval('?>' . $source);
    }

    private static function mac(string $body, string $kind, string $key): string
    {
        return hash_hmac('sha256', "zana-compiled|{$kind}|" . $body, $key);
    }
}
