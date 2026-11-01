<?php

declare(strict_types=1);

namespace Zana\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Core\ConsoleScript;

/**
 * FE-54: hints name the application's own console script, and no script when it is unknown.
 */
final class ConsoleScriptTest extends TestCase
{
    #[After]
    public function forget(): void
    {
        ConsoleScript::record(null);
    }

    public function testWithoutAKnownScriptHintsNameTheCommandOnly(): void
    {
        $this->assertNull(ConsoleScript::current());
        $this->assertSame('the console command `migrate`', ConsoleScript::command('migrate'));
    }

    public function testTheRecordedScriptIsUsedRelativeToTheApplication(): void
    {
        ConsoleScript::record('bin/zana');
        $this->assertSame('`php bin/zana migrate`', ConsoleScript::command('migrate'));

        ConsoleScript::record('C:\\sites\\shop\\bin\\console', 'C:/sites/shop');
        $this->assertSame('bin/console', ConsoleScript::current());
        $this->assertSame('`php bin/console mail:test you@example.com`', ConsoleScript::command('mail:test you@example.com'));

        ConsoleScript::record('/usr/local/bin/shop-cli', '/var/www/shop');
        $this->assertSame('/usr/local/bin/shop-cli', ConsoleScript::current(), 'a script outside the application stays absolute');
    }
}
