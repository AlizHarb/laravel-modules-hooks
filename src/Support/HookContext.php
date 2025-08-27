<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Support;

/**
 * Represents the context of a hook execution.
 *
 * This object is passed to hook callbacks and provides
 * metadata, control over execution, and contextual information.
 *
 * Example usage:
 * ```php
 * $context = new HookContext();
 * $context->userId = auth()->id();
 * $context->meta['foo'] = 'bar';
 * Hooks::dispatch('my_hook', $context);
 * ```
 */
class HookContext
{
    /**
     * Indicates whether the hook execution should be stopped.
     *
     * @var bool
     */
    public bool $stopped = false;

    /**
     * Arbitrary metadata associated with the hook execution.
     *
     * @var array<string, mixed>
     */
    public array $meta = [];

    /**
     * ID of the user associated with the hook context, if applicable.
     *
     * @var int|null
     */
    public ?int $userId = null;

    /**
     * Name of the module that triggered or owns this hook, if any.
     *
     * @var string|null
     */
    public ?string $module = null;

    /**
     * Optional cache key for storing or retrieving hook-related data.
     *
     * @var string|null
     */
    public ?string $cacheKey = null;

    /**
     * Stop further execution of hooks.
     *
     * Once called, subsequent hooks can check `$context->stopped` to
     * determine whether to skip execution.
     */
    public function stop(): void
    {
        $this->stopped = true;
    }
}
