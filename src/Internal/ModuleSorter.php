<?php

declare(strict_types=1);

namespace Zana\Core\Internal;

use Zana\Core\ApplicationException;
use Zana\Core\Module;

/**
 * Orders modules so every module comes after the modules it depends on.
 * Modules without a dependency between them keep their declared order.
 *
 * @internal
 */
final class ModuleSorter
{
    /**
     * @param list<Module> $modules
     * @return list<Module>
     * @throws ApplicationException For an unknown dependency or a cycle
     */
    public static function sort(array $modules): array
    {
        $byClass = [];
        foreach ($modules as $module) {
            $byClass[$module::class] = $module;
        }

        foreach ($modules as $module) {
            foreach ($module->dependsOn() as $dependency) {
                if (!isset($byClass[$dependency])) {
                    throw new ApplicationException(sprintf(
                        'Module %s depends on %s, which is not enabled. Add it to config/modules.php.',
                        $module->name(),
                        $dependency
                    ));
                }
            }
        }

        $sorted = [];
        $placed = [];
        while (count($sorted) < count($modules)) {
            $progress = false;
            foreach ($modules as $module) {
                if (isset($placed[$module::class])) {
                    continue;
                }
                $ready = array_all($module->dependsOn(), static fn (string $d): bool => isset($placed[$d]));
                if ($ready) {
                    $sorted[] = $module;
                    $placed[$module::class] = true;
                    $progress = true;
                    break; // restart so declared order wins among ready modules
                }
            }

            if (!$progress) {
                throw new ApplicationException('Module dependency cycle: ' . self::describeCycle($byClass, $placed) . '.');
            }
        }

        return $sorted;
    }

    /**
     * @param array<class-string, Module> $byClass
     * @param array<class-string, true> $placed
     */
    private static function describeCycle(array $byClass, array $placed): string
    {
        $remaining = array_diff_key($byClass, $placed);
        $current = array_key_first($remaining);
        $path = [];

        while ($current !== null && !in_array($current, $path, true)) {
            $path[] = $current;
            $next = null;
            foreach ($byClass[$current]->dependsOn() as $dependency) {
                if (isset($remaining[$dependency])) {
                    $next = $dependency;
                    break;
                }
            }
            $current = $next;
        }

        $start = $current === null ? 0 : (int) array_search($current, $path, true);
        $cycle = [...array_slice($path, $start), $current ?? $path[0]];

        return implode(' → ', array_map(static fn (string $class): string => $byClass[$class]->name(), $cycle));
    }
}
