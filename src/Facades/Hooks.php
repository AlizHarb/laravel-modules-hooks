<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for accessing the Hook Manager service.
 *
 * Provides a static interface to interact with registered hooks in the application.
 *
 * Usage:
 * ```php
 * use AlizHarb\LaravelModuleHooks\Facades\Hooks;
 *
 * Hooks::register('my_hook', function () { ... });
 * Hooks::dispatch('my_hook', $payload);
 * ```
 *
 * @method static void register(string $name, callable $callback, int $priority = 50)
 * @method static mixed dispatch(string $name, mixed ...$params)
 * @method static bool has(string $name)
 *
 * @see \AlizHarb\LaravelModuleHooks\Support\HookManager
 */
class Hooks extends Facade
{
    /**
     * Get the registered name of the component in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'hooks';
    }
}
