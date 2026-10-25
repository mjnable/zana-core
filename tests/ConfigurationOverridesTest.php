<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Zana\Core\Application;

final class ConfigurationOverridesTest extends TestCase
{
    private string $base;

    #[Before]
    public function createApp(): void
    {
        $this->base = sys_get_temp_dir() . '/zana-core-overrides-' . bin2hex(random_bytes(4));
        mkdir("{$this->base}/config", 0777, true);
        file_put_contents("{$this->base}/config/site.php", '<?php return ["url" => "https://example.com", "name" => "Site", "locales" => ["en", "fr"]];');
    }

    #[After]
    public function cleanUp(): void
    {
        foreach (['APP_SITE__URL', 'APP_ENV', 'FROM_DOTENV'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        @unlink("{$this->base}/.env");
        @unlink("{$this->base}/.env.testing");
        @unlink("{$this->base}/config/site.php");
        @rmdir("{$this->base}/config");
        @rmdir($this->base);
    }

    public function testOverridesWinOverFilesAndEnvironmentVariables(): void
    {
        putenv('APP_SITE__URL=https://from-env.example');

        $config = Application::configure($this->base)
            ->withEnvironment('testing')
            ->withConfiguration(['site.url' => 'http://localhost', 'site' => ['locales' => ['fr']]])
            ->withConfiguration(['mail' => ['from' => 'site@example.com']])
            ->build()
            ->config();

        $this->assertSame('http://localhost', $config->string('site.url'));
        $this->assertSame(['fr'], $config->array('site.locales'), 'lists are replaced whole');
        $this->assertSame('Site', $config->string('site.name'), 'other keys are kept');
        $this->assertSame('site@example.com', $config->string('mail.from'), 'overrides accumulate');
    }

    public function testTheTestingEnvironmentLoadsDotEnvTesting(): void
    {
        file_put_contents("{$this->base}/.env", "FROM_DOTENV=developer\n");
        file_put_contents("{$this->base}/.env.testing", "FROM_DOTENV=testing\n");

        Application::configure($this->base)->withEnvironment('testing')->build();
        $this->assertSame('testing', getenv('FROM_DOTENV'));
    }

    public function testOtherEnvironmentsLoadDotEnv(): void
    {
        file_put_contents("{$this->base}/.env", "FROM_DOTENV=developer\n");
        file_put_contents("{$this->base}/.env.testing", "FROM_DOTENV=testing\n");

        Application::configure($this->base)->withEnvironment('development')->build();
        $this->assertSame('developer', getenv('FROM_DOTENV'));
    }

    public function testTestingFallsBackToDotEnvWithoutDotEnvTesting(): void
    {
        file_put_contents("{$this->base}/.env", "FROM_DOTENV=developer\n");
        putenv('APP_ENV=testing');

        Application::configure($this->base)->build();
        $this->assertSame('developer', getenv('FROM_DOTENV'));
    }

    public function testTheBuilderExposesTheBasePath(): void
    {
        $this->assertSame(str_replace('\\', '/', $this->base), Application::configure($this->base . '/')->basePath());
    }
}
