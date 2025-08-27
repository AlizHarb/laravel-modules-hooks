# Laravel Module Hooks

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Latest Version on
Packagist](https://img.shields.io/packagist/v/alizharb/laravel-meta.svg?style=flat-square)](https://packagist.org/packages/alizharb/laravel-meta)
[![Total
Downloads](https://img.shields.io/packagist/dt/alizharb/laravel-meta.svg?style=flat-square)](https://packagist.org/packages/alizharb/laravel-meta)s

**Laravel Module Hooks** is a fully-featured, modular hook system for Laravel, designed to make your modules easily extensible and decoupled.  
It allows developers to register actions, filters, and short-circuiting hooks using PHP callables, class methods, invokable classes, or PHP 8 attributes.  
The package also supports **widget hooks** that render Blade views, caching, auto-discovery, and convenient Blade directives.

---

## 🚀 Installation

Install the package via Composer:

```bash
composer require alizharb/laravel-module-hooks
```

The service provider is automatically registered using [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools).

Publish the configuration file to customize hook discovery, cache settings, and other options:

```bash
php artisan vendor:publish --tag=laravel-module-hooks-config
```

---

## ⚙️ Configuration

The configuration file is located at:

```
config/laravel-module-hooks.php
```

Available options include:

- Enable/disable auto-discovery of hook attributes
- Define namespaces and paths for scanning
- Configure default caching behavior for Blade hooks

Developers can merge or override this configuration in their own modules if needed.

---

## 📦 Publishing Stubs

The package provides stub templates for generating hooks and widget views.  
Publish them with:

```bash
php artisan vendor:publish --tag=laravel-module-hooks-stubs
```

Available stubs:

- `hook.stub` – for standard hooks
- `hook-widget.stub` – for hooks that render views
- `hook-view.stub` – for default Blade view templates

---

## 🖋️ Blade Directives

The package registers two main directives:

### `@hook`

```blade
@hook('hook.name', $payload = null, $cacheKey = null, $ttl = null)
```

- Executes all registered callbacks for a hook
- Echoes the results
- Supports optional caching with cache keys and TTLs

### `@filter`

```blade
@filter('hook.name', $value)
```

- Passes a value through all filter callbacks for the given hook
- Returns the filtered result

---

## 🧩 Hooks

Hooks can be registered **manually** via the `HookManager`, or **automatically discovered** using PHP attributes.

Features:

- Multiple priorities
- One-time execution
- Stopping propagation
- Caching results

The `HookManager` provides methods for:

- **action()** → Executes callbacks for side effects and collects results
- **filter()** → Passes a value through all callbacks sequentially
- **until()** → Executes callbacks until a truthy or non-null result is returned

---

## 🔍 Auto-discovery

When enabled, the package scans configured namespaces and paths for PHP classes and methods with the `#[Hook]` attribute.  
These hooks are automatically registered without additional configuration.

This allows modules to declare hooks cleanly in code.

---

## 🖼️ Widget Hooks

You can generate a hook class that renders a Blade view using the `--widgets` option:

```bash
php artisan module:make-hook {hookName} {moduleName} {--widgets}
```

- `{hookName}` → The name of the hook (e.g., `latestInventory`)
- `{moduleName}` → The module where the hook should be created (e.g., `Inventory`)
- `--widgets` → Optionally generate a Blade view for widget hooks

This will create:

- Hook class in: `Modules/{Module}/app/Hooks/`
- Corresponding view in: `Modules/{Module}/resources/views/hooks/`

Usage in Blade:

```blade
@hook('hookname')
```

---

## 🛠️ Example Usage

### Defining a Widget Hook

```php
#[Hook('dashboard.widgets', priority: 40)]
public function latestInventoryWidget($payload, HookContext $ctx)
{
    return view('inventory::hooks.latest-inventory')->render();
}
```

### Using in Blade

```blade
@hook('dashboard.widgets')
```

---

## 📜 License

This package is open-source software licensed under the **MIT license**.

---

## 💡 Summary

Laravel Module Hooks makes modular Laravel applications more **flexible, maintainable, and extensible**, providing a unified way to manage:

- ✅ Hooks
- ✅ Filters
- ✅ Widget integration

across all your modules.
