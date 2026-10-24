<?php

declare(strict_types=1);

namespace Modules\Patients;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Core\Tests\Fixtures\Journal;

final class PatientsModule extends Module
{
    public function name(): string
    {
        return 'Patients';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        Journal::$entries[] = 'register:Patients';
        $services->singleton(PatientDirectory::class);
    }

    public function boot(ContainerInterface $container): void
    {
        Journal::$entries[] = 'boot:Patients';
    }
}
