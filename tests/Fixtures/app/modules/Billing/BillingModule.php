<?php

declare(strict_types=1);

namespace Modules\Billing;

use Modules\Patients\PatientsModule;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Core\Tests\Fixtures\Journal;

final class BillingModule extends Module
{
    public function name(): string
    {
        return 'Billing';
    }

    public function dependsOn(): array
    {
        return [PatientsModule::class];
    }

    public function register(ServiceCollectionInterface $services): void
    {
        Journal::$entries[] = 'register:Billing';
        $services->singleton(Invoicer::class);
    }

    public function boot(ContainerInterface $container): void
    {
        Journal::$entries[] = 'boot:Billing';
    }
}
