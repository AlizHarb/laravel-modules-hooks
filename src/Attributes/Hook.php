<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Attributes;

use Attribute;

/**
 * Represents a Hook attribute that can be attached to classes or methods.
 *
 * Hooks are used to extend or modify the behavior of modules dynamically.
 * This attribute can be repeated on the same target and supports prioritization
 * and one-time execution.
 *
 * Usage:
 * ```php
 * #[Hook(name: 'my_hook', priority: 100, once: true)]
 * public function handleMyHook() { ... }
 * ```
 *
 * @see \AlizHarb\LaravelModuleHooks\Support\HookManager
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Hook
{
    /**
     * @param string $name The unique name of the hook.
     * @param int $priority The priority of the hook; lower numbers run first (default 50).
     * @param bool $once Whether this hook should only be executed once (default false).
     */
    public function __construct(
        public string $name,
        public int $priority = 50,
        public bool $once = false
    ) {}
}
