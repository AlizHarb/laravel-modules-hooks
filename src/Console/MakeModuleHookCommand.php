<?php

namespace AlizHarb\LaravelModuleHooks\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Artisan command to generate a hook class inside a module.
 *
 * This command can optionally generate the hook as a widget with a Blade view.
 *
 * Usage:
 * ```
 * php artisan module:make-hook {hook} {module} {--widgets}
 * ```
 *
 * Arguments:
 * - hook: The name of the hook to generate.
 * - module: The module where the hook should be created.
 *
 * Options:
 * - --widgets: If set, generates the hook as a widget and creates a corresponding Blade view.
 */
class MakeModuleHookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-hook {hook} {module} {--widgets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a hook class inside a module, optionally as a widget';

    /**
     * Filesystem instance for file operations.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int Command exit code (0 = success, 1 = failure)
     */
    public function handle(): int
    {
        /** @var string $hookName Name of the hook */
        $hookName = $this->argument('hook');

        /** @var string $moduleName Name of the module */
        $moduleName = $this->argument('module');

        /** @var bool $isWidget Whether to generate a widget view */
        $isWidget = $this->option('widgets');

        /** @var string $className StudlyCase class name for the hook */
        $className = Str::studly($hookName) . 'Hook';

        /** @var string $hookPath Path to the module's Hooks directory */
        $hookPath = base_path("Modules/{$moduleName}/app/Hooks");

        if (!$this->files->isDirectory($hookPath)) {
            $this->files->makeDirectory($hookPath, 0755, true);
        }

        /** @var string $filePath Full path of the hook file */
        $filePath = $hookPath . '/' . $className . '.php';

        if ($this->files->exists($filePath)) {
            $this->error("Hook {$className} already exists in module {$moduleName}!");
            return 1;
        }

        /** @var string $stubPath Path to the stub file */
        $stubPath = $isWidget
            ? __DIR__ . '/../../stubs/hook-widget.stub'
            : __DIR__ . '/../../stubs/hook.stub';

        /** @var string $stub Contents of the stub file */
        $stub = $this->files->get($stubPath);

        $stub = str_replace(
            ['{{module}}', '{{class}}', '{{hook}}'],
            [$moduleName, $className, $hookName],
            $stub
        );

        $this->files->put($filePath, $stub);

        if ($isWidget) {
            /** @var string $viewPath Path to the module's hook views directory */
            $viewPath = base_path("Modules/{$moduleName}/resources/views/hooks");

            if (!$this->files->isDirectory($viewPath)) {
                $this->files->makeDirectory($viewPath, 0755, true);
            }

            /** @var string $viewFile Full path for the widget view */
            $viewFile = $viewPath . '/' . Str::kebab($hookName) . '.blade.php';

            if (!$this->files->exists($viewFile)) {
                $viewStub = $this->files->get(__DIR__ . '/../../stubs/hook-view.stub');
                $viewStub = str_replace('{{hook}}', Str::kebab($hookName), $viewStub);
                $this->files->put($viewFile, $viewStub);
            }

            $this->info("Hook {$className} created successfully in module {$moduleName}!");
            $this->info("Widget view created at: Modules/{$moduleName}/resources/views/hooks/" . Str::kebab($hookName) . ".blade.php");
            $this->info("You can use it in Blade with: @hook('{$hookName}')");
        } else {
            $this->info("Hook {$className} created successfully in module {$moduleName}!");
        }

        return 0;
    }
}
