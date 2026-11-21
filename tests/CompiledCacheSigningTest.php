<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zana\Core\Application;
use Zana\Core\Tests\Fixtures\FrameworkProvider;

/**
 * 2.35: the compiled container and the configuration cache are signed with a
 * key derived from security.secret, and checked before they are included, so
 * code written to var/cache by anything but the application does not run.
 *
 * The first boot writes the files in this process; each later one is a
 * request of its own, in a new PHP process.
 */
final class CompiledCacheSigningTest extends TestCase
{
    private const string SECRET = 'a-secret-of-this-application-0123456789abcdef';

    private string $base = '';

    protected function setUp(): void
    {
        $this->base = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-core-signed-' . bin2hex(random_bytes(6));
        mkdir("{$this->base}/config", 0777, true);
        file_put_contents("{$this->base}/config/site.php", "<?php return ['name' => 'Acme'];");
    }

    #[After]
    public function cleanUp(): void
    {
        putenv('APP_SECURITY__SECRET');
        exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $this->base)) : 'rm -rf ' . escapeshellarg($this->base));
    }

    public function testBothCachesAreSignedWithTheSecretFromTheEnvironment(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);

        $this->assertTrue($this->boot()->compiledCachesSigned());
        foreach ($this->files() as $file) {
            $this->assertMatchesRegularExpression('/\A<\?php\n\/\/ zana-signature: [0-9a-f]{64}\n/', (string) file_get_contents($file));
        }
        $written = array_map(file_get_contents(...), $this->files());

        $this->assertSame('ok Acme', $this->request());
        $this->assertSame($written, array_map(file_get_contents(...), $this->files()), 'read back, not rewritten');
    }

    public function testASecretFromWithConfigurationSignsToo(): void
    {
        $this->assertTrue($this->boot(overrides: ['security.secret' => self::SECRET])->compiledCachesSigned());
        $this->assertStringStartsWith("<?php\n// zana-signature: ", (string) file_get_contents($this->files()['container']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function kinds(): iterable
    {
        yield 'the compiled container' => ['container'];
        yield 'the configuration cache' => ['configuration'];
    }

    #[DataProvider('kinds')]
    public function testCodeAddedToASignedCacheDoesNotRun(string $kind): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        $this->plant($kind, keepSignature: true);

        $result = $this->request();

        $this->assertStringContainsString("The {$kind} cache", $result);
        $this->assertStringContainsString("does not carry this application's signature", $result);
        $this->assertStringNotContainsString('planted', $result, 'the planted code did not run');
    }

    #[DataProvider('kinds')]
    public function testAnUnsignedCacheIsRewrittenNotIncluded(string $kind): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        $this->plant($kind, keepSignature: false);   // as a file written by 2.34 would be, with code added

        $this->assertSame('ok Acme', $this->request());
        $source = (string) file_get_contents($this->files()[$kind]);
        $this->assertStringStartsWith("<?php\n// zana-signature: ", $source, 'rewritten, signed');
        $this->assertStringNotContainsString('planted', $source);
        $this->assertSame('ok Acme', $this->request(), 'and read back');
    }

    public function testACacheSignedWithAnotherSecretStopsTheBootButNotTheConsole(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        putenv('APP_SECURITY__SECRET=the-secret-after-a-rotation-0123456789abcdef');

        $this->assertStringContainsString('Run cache:warm', $this->request(), 'a request on caches signed with the old secret');
        $this->assertSame('ok Acme', $this->request(console: true), 'bin/console, so that cache:warm can run');
        $this->assertSame('ok Acme', $this->request(), 'both files signed again, with the new secret');
    }

    #[DataProvider('kinds')]
    public function testIncludedCachesAreCheckedToo(string $kind): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();

        $this->assertSame('ok Acme', $this->request(include: true));
        $this->plant($kind, keepSignature: true);
        $this->assertStringContainsString("The {$kind} cache", $this->request(include: true));
        $this->plant($kind, keepSignature: false);
        $result = $this->request(include: true);
        $this->assertSame('ok Acme', $result, 'unsigned: rewritten, not included');
    }

    /**
     * The bytes checked are the bytes that run: a signed file is evaluated, not included after the check (which a
     * file swapped in between would win), unless includeCompiledCaches() asks for OPcache.
     */
    public function testSignedFilesAreEvaluatedUnlessIncludingIsAsked(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        $file = $this->files()['configuration'];
        $key = \Zana\Core\Internal\CompiledFile::key([]);
        $this->assertIsString($key);
        $source = "<?php\nreturn ['environment' => 'production', 'modules' => [], 'values' => ['site' => ['name' => basename(__FILE__)]]];\n";
        file_put_contents($file, \Zana\Core\Internal\CompiledFile::sign($source, 'configuration', $key));

        $this->assertStringContainsString("eval()'d code", $this->request());
        $this->assertSame('ok config.php', $this->request(include: true));
    }

    /**
     * A process whose container went stale reads the file again, in case another process rewrote it: that file is
     * checked too.
     */
    public function testAFileReadAgainAfterAStaleContainerIsChecked(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();                                                    // this process loads the container
        $source = (string) file_get_contents($this->files()['container']);
        $planted = preg_replace('/^(namespace [^;]+;\r?\n)/m', "\$1\$GLOBALS['zana_planted'] = true;\n", (string) preg_replace('/Container([0-9a-f]{16})/', 'Planted$1', $source), 1);
        file_put_contents($this->files()['container'], $planted);        // "another process" writes a planted file
        unset($GLOBALS['zana_planted']);

        try {
            Application::configure($this->base)
                ->withEnvironment('production')
                ->withProviders(FrameworkProvider::class)
                ->withServices(static fn (\Zana\Container\Contracts\ServiceCollectionInterface $s) => $s->singleton(\ArrayObject::class))
                ->withContainerCache($this->files()['container'])
                ->withConfigurationCache($this->files()['configuration'])
                ->withConfiguration(['app.deployment_checks' => false])
                ->build();
            $this->fail('the boot went on');
        } catch (\Zana\Core\ApplicationException $e) {
            $this->assertStringContainsString("does not carry this application's signature", $e->getMessage());
        }
        $this->assertArrayNotHasKey('zana_planted', $GLOBALS, 'the planted code did not run');
    }

    public function testIncludingIsDeclaredToTheConfiguration(): void
    {
        $app = Application::configure($this->base)->withEnvironment('testing')->includeCompiledCaches()->build();
        $this->assertTrue($app->config()->bool('app.include_compiled_caches'));
        $this->assertFalse(Application::configure($this->base)->withEnvironment('testing')->build()->config()->bool('app.include_compiled_caches', false));
    }

    public function testOneKindOfFileCannotStandForTheOther(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        copy($this->files()['configuration'], $this->files()['container']);

        $this->assertStringContainsString("The container cache {$this->files()['container']} does not carry", $this->request());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function signatureLines(): iterable
    {
        yield 'empty' => ['empty'];
        yield 'in capitals' => ['upper'];
        yield 'shortened' => ['short'];
        yield 'no line break after it' => ['joined'];
        yield 'one character changed' => ['changed'];
        yield 'the file cut after it' => ['cut'];
    }

    #[DataProvider('signatureLines')]
    public function testMalformedSignaturesAreRefused(string $line): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot();
        $file = $this->files()['configuration'];
        $source = (string) file_get_contents($file);
        if (preg_match('/\A<\?php\n\/\/ zana-signature: ([0-9a-f]{64})\n/', $source, $m) !== 1) {
            $this->fail('the file is not signed');
        }
        $rest = $line === 'cut' ? '' : substr($source, strlen($m[0]));
        $mac = $m[1];
        file_put_contents($file, "<?php\n// zana-signature: " . match ($line) {
            'empty' => "\n",
            'upper' => strtoupper($mac) . "\n",
            'short' => substr($mac, 0, 32) . "\n",
            'joined', 'cut' => $mac,
            default => ($mac[0] === 'a' ? 'b' : 'a') . substr($mac, 1) . "\n",   // changed
        } . $rest);

        $this->assertStringContainsString("does not carry this application's signature", $this->request());
    }

    public function testOutsideProductionAChangedCacheIsRewritten(): void
    {
        putenv('APP_SECURITY__SECRET=' . self::SECRET);
        $this->boot('development', always: true);
        $this->plant('container', keepSignature: true);

        $this->assertSame('ok Acme', $this->request('development', always: true));
        $this->assertStringNotContainsString('planted', (string) file_get_contents($this->files()['container']));
    }

    public function testWithoutASecretNothingIsSignedAsBefore(): void
    {
        $this->assertFalse($this->boot()->compiledCachesSigned());
        foreach ($this->files() as $file) {
            $this->assertStringNotContainsString('zana-signature', (string) file_get_contents($file));
        }
        $this->assertSame('ok Acme', $this->request());

        $this->plant('configuration', keepSignature: false);
        $this->assertSame('planted ok Acme', $this->request(), 'the documented limit: without a key, nothing is checked');
    }

    public function testASecretOnlyInAConfigurationFileCannotSignTheCacheThatHoldsIt(): void
    {
        file_put_contents("{$this->base}/config/security.php", "<?php return ['secret' => '" . self::SECRET . "'];");

        $app = $this->boot();

        $this->assertSame(self::SECRET, $app->config()->string('security.secret'));
        $this->assertFalse($app->compiledCachesSigned());
        $this->assertStringNotContainsString('zana-signature', (string) file_get_contents($this->files()['configuration']));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function boot(string $environment = 'production', bool $always = false, array $overrides = []): Application
    {
        return Application::configure($this->base)
            ->withEnvironment($environment)
            ->withProviders(FrameworkProvider::class)
            ->withContainerCache($this->files()['container'], $always)
            ->withConfigurationCache($this->files()['configuration'])
            ->withConfiguration(['app.deployment_checks' => false, ...$overrides])
            ->build();
    }

    /**
     * One request in a PHP process of its own, as the front controller (or bin/console) boots: "ok" and the site's
     * name, or the error, preceded by "planted" when code planted in a cache ran.
     */
    private function request(string $environment = 'production', bool $always = false, bool $console = false, bool $include = false): string
    {
        $script = "{$this->base}/request.php";
        file_put_contents($script, '<?php
require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';
try {
    $builder = Zana\Core\Application::configure(' . var_export($this->base, true) . ')
        ->withEnvironment(' . var_export($environment, true) . ')
        ->withProviders(Zana\Core\Tests\Fixtures\FrameworkProvider::class)
        ->withContainerCache(' . var_export($this->files()['container'], true) . ', ' . var_export($always, true) . ')
        ->withConfigurationCache(' . var_export($this->files()['configuration'], true) . ')
        ->withConfiguration(["app.deployment_checks" => false]);
    if (' . var_export($console, true) . ') {
        $builder->recompileStaleContainerInConsole();
    }
    if (' . var_export($include, true) . ') {
        $builder->includeCompiledCaches();
    }
    $app = $builder->build();
    $app->container()->get(Zana\Core\Tests\Fixtures\Clock::class);
    $result = "ok " . $app->config()->string("site.name");
} catch (Throwable $e) {
    $result = get_class($e) . ": " . $e->getMessage();
}
echo (isset($GLOBALS["zana_planted"]) ? "planted " : "") . $result;');

        return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d xdebug.mode=off ' . escapeshellarg($script) . ' 2>&1');
    }

    /**
     * @return array{container: string, configuration: string}
     */
    private function files(): array
    {
        return ['container' => "{$this->base}/var/cache/container.php", 'configuration' => "{$this->base}/var/cache/config.php"];
    }

    /**
     * Add code to a cache file, as someone who can write to var/cache could, keeping its signature line or not.
     */
    private function plant(string $kind, bool $keepSignature): void
    {
        $file = $this->files()[$kind];
        $source = (string) file_get_contents($file);
        if (!$keepSignature) {
            $source = (string) preg_replace('/\A(<\?php\n)\/\/ zana-signature: [0-9a-f]{64}\n/', '$1', $source);
        }
        $code = "\$GLOBALS['zana_planted'] = true;\n";
        $planted = $kind === 'container'
            ? preg_replace('/^(namespace [^;]+;\r?\n)/m', '$1' . $code, $source, 1)
            : preg_replace('/^(return )/m', $code . '$1', $source, 1);
        $this->assertIsString($planted);
        $this->assertNotSame($source, $planted);
        file_put_contents($file, $planted);
    }
}
