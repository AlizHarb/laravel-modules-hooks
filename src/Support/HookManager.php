<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages hooks and their execution in the application.
 *
 * Provides registration, removal, and execution of hooks with
 * priorities, one-time execution, and wildcard matching.
 *
 * Three main execution patterns:
 * - `filter`: transform a value through hooks like a pipeline.
 * - `action`: run hooks for side-effects and collect results.
 * - `until`: short-circuit execution on first truthy result.
 *
 * @example
 * ```php
 * Hooks::add('dashboard.init', fn($value) => $value + 1);
 * $result = Hooks::filter('dashboard.init', 0);
 * ```
 */
final class HookManager
{
    /**
     * Registry of hooks.
     *
     * Structure:
     * [hook_name][priority][] = ['cb' => callable|string, 'once' => bool, 'id' => string]
     *
     * @var array<string, array<int, array<int, array{cb: callable|string, once: bool, id: string}>>>
     */
    private array $registry = [];

    /**
     * Tracks currently running hooks to prevent re-entrancy.
     *
     * @var array<string, bool>
     */
    private array $running = [];

    /**
     * Tracks registered hook IDs to prevent duplicate registration.
     *
     * @var array<string, bool>
     */
    private array $registeredIds = [];

    /**
     * Constructor.
     *
     * @param Container $container Laravel container for resolving classes.
     */
    public function __construct(private Container $container) {}

    // --- Registration ---

    /**
     * Register a hook callback.
     *
     * @param string $hook Hook name.
     * @param callable|string $callback Callable, "Class@method", or invokable class FQCN.
     * @param int $priority Execution priority; lower numbers run earlier (default 50).
     * @param bool $once Whether to run only once (default false).
     * @param string|null $id Optional unique ID to prevent duplicate registration.
     */
    public function add(string $hook, callable|string $callback, int $priority = 50, bool $once = false, ?string $id = null): void
    {
        $id ??= $this->makeId($hook, $callback, $priority);
        if (isset($this->registeredIds[$id])) {
            return;
        }
        $this->registeredIds[$id] = true;
        $this->registry[$hook][$priority][] = ['cb' => $callback, 'once' => $once, 'id' => $id];
    }

    /**
     * Remove a hook or a specific callback by ID.
     *
     * @param string $hook Hook name.
     * @param string|null $id Specific callback ID to remove; if null, removes all callbacks.
     */
    public function remove(string $hook, ?string $id = null): void
    {
        if (!isset($this->registry[$hook])) return;
        if ($id === null) {
            unset($this->registry[$hook]);
            return;
        }
        foreach ($this->registry[$hook] as $prio => &$items) {
            $items = array_values(array_filter($items, fn($i) => $i['id'] !== $id));
        }
    }

    /**
     * Check if a hook exists, including wildcard matches.
     *
     * @param string $hook Hook name.
     * @return bool
     */
    public function has(string $hook): bool
    {
        if (isset($this->registry[$hook])) return true;
        foreach ($this->registry as $name => $_) {
            if ($this->matches($name, $hook)) return true;
        }
        return false;
    }

    // --- Execution ---

    /**
     * FILTER: pass a value through callbacks like a pipeline.
     *
     * @param string $hook Hook name.
     * @param mixed $value Initial value to filter.
     * @param HookContext $ctx Hook execution context.
     * @return mixed Filtered value.
     */
    public function filter(string $hook, mixed $value, HookContext $ctx = new HookContext()): mixed
    {
        foreach ($this->iterCallbacks($hook) as $entry) {
            $cb = $this->resolve($entry['cb']);
            $this->guardStart($hook);
            try {
                $value = $cb($value, $ctx);
            } catch (Throwable $e) {
                $this->report($hook, $e);
            } finally {
                $this->guardEnd($hook);
            }
            if ($entry['once']) $this->remove($hook, $entry['id']);
            if ($ctx->stopped) break;
        }
        return $value;
    }

    /**
     * ACTION: run callbacks for side effects and collect results.
     *
     * @param string $hook Hook name.
     * @param mixed $payload Payload passed to each callback.
     * @param HookContext $ctx Hook execution context.
     * @return Collection<int, mixed> Collected results.
     */
    public function action(string $hook, mixed $payload = null, HookContext $ctx = new HookContext()): Collection
    {
        $results = collect();
        foreach ($this->iterCallbacks($hook) as $entry) {
            $cb = $this->resolve($entry['cb']);
            $this->guardStart($hook);
            try {
                $results->push($cb($payload, $ctx));
            } catch (Throwable $e) {
                $this->report($hook, $e);
            } finally {
                $this->guardEnd($hook);
            }
            if ($entry['once']) $this->remove($hook, $entry['id']);
            if ($ctx->stopped) break;
        }
        return $results;
    }

    /**
     * UNTIL: run callbacks until first non-null/true-ish result.
     *
     * @param string $hook Hook name.
     * @param mixed $payload Payload passed to each callback.
     * @param HookContext $ctx Hook execution context.
     * @return mixed|null First non-null result or null if none.
     */
    public function until(string $hook, mixed $payload = null, HookContext $ctx = new HookContext()): mixed
    {
        foreach ($this->iterCallbacks($hook) as $entry) {
            $cb = $this->resolve($entry['cb']);
            $this->guardStart($hook);
            try {
                $res = $cb($payload, $ctx);
            } catch (Throwable $e) {
                $this->report($hook, $e);
                $res = null;
            } finally {
                $this->guardEnd($hook);
            }
            if ($entry['once']) $this->remove($hook, $entry['id']);
            if ($ctx->stopped || $res) return $res;
        }
        return null;
    }

    // --- Internal helpers ---

    /**
     * Yield all callbacks for a hook, including wildcard matches.
     *
     * @param string $hook
     * @return \Generator<array{cb: callable|string, once: bool, id: string}>
     */
    private function iterCallbacks(string $hook): \Generator
    {
        $buckets = $this->registry[$hook] ?? [];
        foreach ($this->registry as $name => $byPrio) {
            if ($name !== $hook && $this->matches($name, $hook)) {
                $buckets = array_replace_recursive($buckets, $byPrio);
            }
        }
        if (empty($buckets)) return;
        ksort($buckets);
        foreach ($buckets as $prio => $items) {
            foreach ($items as $entry) yield $entry;
        }
    }

    /**
     * Resolve a callback string or invokable into a callable.
     *
     * @param callable|string $cb
     * @return callable
     */
    private function resolve(callable|string $cb): callable
    {
        if (is_string($cb)) {
            if (str_contains($cb, '@')) {
                [$class, $method] = explode('@', $cb, 2);
                return fn(...$args) => $this->container->make($class)->{$method}(...$args);
            }
            return fn(...$args) => $this->container->make($cb)(...$args);
        }
        return $cb;
    }

    /**
     * Determine if a hook name matches a wildcard pattern.
     *
     * @param string $pattern e.g., "dashboard.*"
     * @param string $name Actual hook name.
     * @return bool
     */
    private function matches(string $pattern, string $name): bool
    {
        $pattern = str_replace(['*', '.'], ['[^.]+', '\.'], $pattern);
        return (bool) preg_match("/^{$pattern}$/", $name);
    }

    /**
     * Generate a unique ID for a hook callback.
     *
     * @param string $hook
     * @param callable|string $cb
     * @param int $priority
     * @return string
     */
    private function makeId(string $hook, callable|string $cb, int $priority): string
    {
        $sig = is_string($cb) ? $cb : spl_object_hash(Closure::fromCallable($cb));
        return md5($hook . '|' . $sig . '|' . $priority);
    }

    /**
     * Start execution guard for a hook to prevent re-entrancy.
     *
     * @param string $hook
     * @throws \RuntimeException
     */
    private function guardStart(string $hook): void
    {
        if (!empty($this->running[$hook])) {
            throw new \RuntimeException("Re-entrancy detected for hook [$hook].");
        }
        $this->running[$hook] = true;
    }

    /**
     * End execution guard for a hook.
     *
     * @param string $hook
     */
    private function guardEnd(string $hook): void
    {
        unset($this->running[$hook]);
    }

    /**
     * Report a callback exception via logging.
     *
     * @param string $hook
     * @param Throwable $e
     */
    private function report(string $hook, Throwable $e): void
    {
        Log::error("Hook [$hook] callback failed: {$e->getMessage()}", ['exception' => $e]);
    }
}
