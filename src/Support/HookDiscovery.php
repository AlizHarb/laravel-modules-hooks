<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Support;

use AlizHarb\LaravelModuleHooks\Attributes\Hook as HookAttr;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\File;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Discovers hook classes and methods across configured namespaces and paths.
 *
 * This class scans the filesystem for PHP classes and their methods,
 * detects attributes of type `Hook`, and registers them with the HookManager.
 *
 * Example usage:
 * ```php
 * $discovery = new HookDiscovery(app());
 * $discovery->discover();
 * ```
 *
 * @see \AlizHarb\LaravelModuleHooks\Support\HookManager
 * @see \AlizHarb\LaravelModuleHooks\Attributes\Hook
 */
class HookDiscovery
{
    /**
     * Constructor.
     *
     * @param \Illuminate\Contracts\Container\Container $app The Laravel application container.
     */
    public function __construct(private Container $app) {}

    /**
     * Discover and register all hooks from configured paths and namespaces.
     *
     * Scans the directories specified in `laravel-module-hooks.discovery.paths`
     * for PHP files, loads classes, inspects their attributes, and registers
     * hooks with the `HookManager`.
     *
     * @return void
     */
    public function discover(): void
    {
        /** @var string[] $namespaces Namespaces to scan for hook classes */
        $namespaces = config('laravel-module-hooks.discovery.namespaces', []);

        /** @var string[] $paths Directories to scan for hook classes */
        $paths = config('laravel-module-hooks.discovery.paths', []);

        /** @var \AlizHarb\LaravelModuleHooks\Support\HookManager $hooks The hook manager instance */
        $hooks = $this->app->make(HookManager::class);

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromFile($file->getRealPath(), $namespaces);
                if (!$class || !class_exists($class)) {
                    continue;
                }

                $rc = new ReflectionClass($class);

                // Register class-level hooks
                foreach ($rc->getAttributes(HookAttr::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $def = $attr->newInstance();
                    $hooks->add($def->name, $class, $def->priority, $def->once);
                }

                // Register method-level hooks
                foreach ($rc->getMethods() as $rm) {
                    foreach ($rm->getAttributes(HookAttr::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                        $def = $attr->newInstance();
                        $hooks->add($def->name, "{$class}@{$rm->getName()}", $def->priority, $def->once);
                    }
                }
            }
        }
    }

    /**
     * Determine the fully-qualified class name from a PHP file if it matches configured roots.
     *
     * @param string $path The full path to the PHP file.
     * @param string[] $roots Array of root namespaces to filter by.
     * @return string|null Fully-qualified class name if found, or null.
     */
    private function classFromFile(string $path, array $roots): ?string
    {
        $contents = file_get_contents($path);
        if (!preg_match('/namespace\s+([^;]+);/m', $contents, $ns)) {
            return null;
        }

        if (!preg_match('/class\s+([^\s]+)/m', $contents, $cl)) {
            return null;
        }

        $fqcn = trim($ns[1]) . '\\' . trim($cl[1]);

        foreach ($roots as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return $fqcn;
            }
        }

        return null;
    }
}
