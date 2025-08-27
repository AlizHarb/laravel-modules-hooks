<?php

/**
 * Laravel Hook Discovery Configuration.
 *
 * This configuration controls how hooks are discovered and cached in the application.
 * Hooks allow extending or modifying the behavior of modules dynamically.
 *
 * @see \AlizHarb\LaravelModuleHooks\Support\HookDiscovery
 * @see \AlizHarb\LaravelModuleHooks\Support\HookManager
 *
 * @var array{
 *     discovery: array{
 *         enabled: bool,
 *         namespaces: string[],
 *         paths: string[]
 *     },
 *     cache: array{
 *         default_ttl: int
 *     }
 * }
 */
return [
    'discovery' => [
        /** @var bool Whether automatic hook discovery is enabled. */
        'enabled' => true,

        /** 
         * @var string[] PSR-4 namespaces to scan for hook attributes.
         * Modules can add their own namespaces via config merge.
         */
        'namespaces' => [
            'Modules\\',
            'App\\',
        ],

        /**
         * @var string[] Directories relative to base_path to hint scanners for optimization.
         */
        'paths' => [
            base_path('Modules'),
            app_path(),
        ],
    ],

    'cache' => [
        /**
         * @var int Default time-to-live (in seconds) for cached hooks when not specified.
         */
        'default_ttl' => 60,
    ],
];
