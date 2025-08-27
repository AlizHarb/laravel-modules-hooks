<?php

declare(strict_types=1);

namespace AlizHarb\LaravelModuleHooks\Providers;

use AlizHarb\LaravelModuleHooks\Console\MakeModuleHookCommand;
use AlizHarb\LaravelModuleHooks\Support\HookManager;
use AlizHarb\LaravelModuleHooks\Support\HookDiscovery;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelModuleHooksServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-module-hooks')
            ->hasConfigFile('laravel-module-hooks')
            ->hasCommand(MakeModuleHookCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register HookManager singleton
        $this->app->singleton(HookManager::class, fn($app) => new HookManager($app));
        $this->app->alias(HookManager::class, 'hooks');
    }

    public function packageBooted(): void
    {
        // Blade directives
        Blade::directive('hook', function ($expression) {
            return "<?php
                \$__args = [$expression];
                \$name = \$__args[0] ?? '';
                \$payload = \$__args[1] ?? null;
                \$cacheKey = \$__args[2] ?? null;
                \$ttl = \$__args[3] ?? null;
                if (!is_array(\$payload) && !\$payload instanceof \\Traversable) \$payload = [\$payload];
                if (\$cacheKey && \$ttl) {
                    \$cacheKey = 'hook:'.\$name.':'.md5(serialize(\$cacheKey));
                    echo cache()->remember(\$cacheKey, \$ttl, function() use (\$name, \$payload) {
                        return app('hooks')->action(\$name, ...\$payload)->implode('');
                    });
                } else {
                    echo app('hooks')->action(\$name, ...\$payload)->implode('');
                }
            ?>";
        });

        Blade::directive('filter', fn($expression) => "<?php echo app('hooks')->filter(...[$expression]); ?>");

        // Discover attributes if enabled
        if (config('laravel-module-hooks.discovery.enabled')) {
            (new HookDiscovery(app()))->discover();
        }
    }

    public function bootingPackage(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../stubs' => base_path('stubs/laravel-module-hooks'),
            ], 'laravel-module-hooks-stubs');
        }
    }
}
