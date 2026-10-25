<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Core\ApplicationInterface;
use Zana\Core\Deployment\BuiltInChecks;
use Zana\Core\Deployment\CheckResult;
use Zana\Core\Deployment\DeploymentCheck;
use Zana\Core\Deployment\UnsafeDeploymentException;

final class DeploymentChecksTest extends TestCase
{
    #[After]
    public function clearEnvironment(): void
    {
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG']);
    }

    public function testDebugOnAPublicUrlRefusesToStart(): void
    {
        $this->expectException(UnsafeDeploymentException::class);
        $this->expectExceptionMessage('Debug mode is on for the public URL https://example.com');

        $this->builder('development')->withConfiguration(['app.url' => 'https://example.com'])->build();
    }

    public function testDebugOnALocalUrlStarts(): void
    {
        $app = $this->builder('development')->withConfiguration(['app.url' => 'http://site.test'])->build();

        $this->assertTrue($app->environment()->debug);
    }

    public function testDebugInProductionRefusesToStart(): void
    {
        putenv('APP_DEBUG=true');

        $this->expectException(UnsafeDeploymentException::class);
        $this->expectExceptionMessage('Debug mode is on in production');
        $this->builder('production')->build();
    }

    public function testPlaceholderSecretsRefuseToStartInProduction(): void
    {
        try {
            $this->builder('production')->withConfiguration(['security.secret' => 'change-me-please', 'mail.key' => '', 'app.secret_keys' => ['security.secret', 'mail.key']])->build();
            $this->fail('Expected an UnsafeDeploymentException.');
        } catch (UnsafeDeploymentException $e) {
            $this->assertStringContainsString('security.secret is empty or a placeholder', $e->getMessage());
            $this->assertStringContainsString('mail.key is empty or a placeholder', $e->getMessage());
        }

        $this->assertInstanceOf(Application::class, $this->builder('development')->withConfiguration(['security.secret' => 'change-me'])->build(), 'only production');
        $this->assertInstanceOf(Application::class, $this->builder('production')->withConfiguration(['security.secret' => bin2hex(random_bytes(32))])->build());
    }

    public function testApplicationChecksRunAndWarningsAreLogged(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<string> */
            public array $lines = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = "{$level}: {$message}";
            }
        };

        $this->builder('production')->withServices(static fn (ServiceCollectionInterface $s) => $s
            ->instance(LoggerInterface::class, $logger)
            ->singleton(DiskSpaceCheck::class)
            ->tag(DeploymentCheck::TAG, DiskSpaceCheck::class))
            ->build();

        $this->assertSame(['warning: Deployment check: The disk is almost full.'], $logger->lines);
    }

    public function testFatalApplicationChecksStopTheBuild(): void
    {
        $this->expectException(UnsafeDeploymentException::class);
        $this->expectExceptionMessage('Uploads are not writable.');

        $this->builder('production')->withServices(static fn (ServiceCollectionInterface $s) => $s
            ->instance(UploadsCheck::class, new UploadsCheck())
            ->tag(DeploymentCheck::TAG, UploadsCheck::class))
            ->build();
    }

    public function testChecksCanBeDisabled(): void
    {
        putenv('APP_DEBUG=true');

        $app = $this->builder('production')->withConfiguration(['app.deployment_checks' => false])->build();

        $this->assertTrue($app->environment()->debug);
    }

    #[DataProvider('hosts')]
    public function testLocalHosts(string $host, bool $local): void
    {
        $this->assertSame($local, BuiltInChecks::isLocalHost($host));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hosts(): iterable
    {
        yield 'localhost' => ['localhost', true];
        yield 'empty' => ['', true];
        yield 'loopback' => ['127.0.0.1', true];
        yield 'ipv6 loopback' => ['[::1]', true];
        yield 'private' => ['192.168.1.20', true];
        yield 'test suffix' => ['shop.test', true];
        yield 'localhost suffix' => ['api.localhost', true];
        yield 'public name' => ['example.com', false];
        yield 'public address' => ['8.8.8.8', false];
        yield 'look-alike' => ['testshop.com', false];
    }

    private function builder(string $environment): ApplicationBuilder
    {
        return Application::configure(sys_get_temp_dir() . '/zana-core-deployment')->withEnvironment($environment);
    }
}

final class DiskSpaceCheck implements DeploymentCheck
{
    public function check(ApplicationInterface $application): CheckResult
    {
        return CheckResult::warning('The disk is almost full.');
    }
}

final class UploadsCheck implements DeploymentCheck
{
    public function check(ApplicationInterface $application): CheckResult
    {
        return CheckResult::fatal('Uploads are not writable.');
    }
}
