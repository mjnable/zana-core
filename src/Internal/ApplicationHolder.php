<?php

declare(strict_types=1);

namespace Zana\Core\Internal;

use Zana\Core\ApplicationInterface;

/**
 * Lets the container resolve the application, which only exists after the container is built.
 *
 * @internal
 */
final class ApplicationHolder
{
    private ?ApplicationInterface $application = null;

    public function set(ApplicationInterface $application): void
    {
        $this->application = $application;
    }

    public function get(): ApplicationInterface
    {
        return $this->application ?? throw new \LogicException('The application is not built yet; resolve it from boot() onward.');
    }
}
