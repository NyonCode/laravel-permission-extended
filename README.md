# Laravel Permission Extended

[![Tests](https://github.com/NyonCode/laravel-permission-extended/actions/workflows/tests.yml/badge.svg)](https://github.com/NyonCode/laravel-permission-extended/actions/workflows/tests.yml)[![Latest Version on Packagist](https://img.shields.io/packagist/v/nyoncode/laravel-permission-extended.svg)](https://packagist.org/packages/nyoncode/laravel-permission-extended)
[![Total Downloads](https://img.shields.io/packagist/dt/nyoncode/laravel-permission-extended.svg)](https://packagist.org/packages/nyoncode/laravel-permission-extended)
[![License](https://img.shields.io/packagist/l/nyoncode/laravel-permission-extended.svg)](https://packagist.org/packages/nyoncode/laravel-permission-extended)

Wildcard permissions, super-admin gate, auto middleware registration, Blade directives and Livewire support — all built on [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

## Support

|             | Laravel 10 | Laravel 11 | Laravel 12 | Laravel 13 |
|-------------|:---:|:---:|:-----------:|:----------:|
| **PHP 8.2** | ✅ | ✅ |      ✅      |     -      |
| **PHP 8.3** | ✅ | ✅ |      ✅      |     ✅      |
| **PHP 8.4** | ✅ | ✅ |      ✅      |     ✅      |
| **PHP 8.5** | — | ✅ |      ✅      |     ✅      |

```php
// Wildcards just work in every Spatie method
$user->hasPermissionTo('admin.*');
$user->hasRoleOrPermission('editor|admin.*');

// Super-admin bypasses everything via Gate
@can('anything') YES @endcan

// Spatie middleware registered automatically
Route::middleware('permission:admin.*')
```

## Installation

```bash
composer require nyoncode/laravel-permission-extended
php artisan permission-extended:install
```

The install command will publish config, Spatie migrations, run migrations and optionally add the trait to your User model.

### Manual Setup

Change the `HasRoles` import on your User model — from Spatie to this package:

```php
// Before:  use Spatie\Permission\Traits\HasRoles;
// After:
use NyonCode\PermissionExtended\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

> **Do not** use both Spatie's and this package's `HasRoles` on the same model — this one already includes Spatie's internally.

## Features

### Wildcard Permissions

```php
$user->hasPermissionTo('admin.*');                   // any admin permission
$user->hasAnyPermission(['admin.*', 'posts.edit']);   // any match
$user->hasAllPermissions(['admin.*', 'posts.*']);     // all must match
$user->hasRoleOrPermission('editor|admin.*');         // role OR permission
```

`@can('admin.*')`, `Gate::allows('admin.*')` and `middleware('permission:admin.*')` all work transparently.

### Super-Admin Role

Configured via `config/permission-extended.php`:

```php
'super_admin_role' => 'super-admin', // or null to disable
```

Users with this role pass every `Gate::allows()` / `@can()` / `$user->can()` check automatically. No need to assign individual permissions.

### Automatic Middleware Registration

Spatie's middleware is registered automatically — no manual setup in `bootstrap/app.php`:

```php
// These just work out of the box:
Route::middleware('role:admin')
Route::middleware('permission:admin.*')
Route::middleware('role_or_permission:editor|admin.*')
```

Disable via `'register_middleware' => false` in config.

### Blade Directives

```blade
@canPermission('admin.*')              @endcanPermission
@canAnyPermission(['a.*', 'b.*'])      @endcanAnyPermission
@canAllPermissions(['a.*', 'b.*'])     @endcanAllPermissions
@hasRoleOrPermission('admin|a.*')      @endhasRoleOrPermission
@unlessCanPermission('admin.*')        @endunlessCanPermission
```

Native `@can('admin.*')` also works.

### Livewire Integration

```php
use NyonCode\PermissionExtended\Livewire\WithPermissions;

class AdminPanel extends Component
{
    use WithPermissions;

    public function mount()
    {
        $this->permAuthorize('admin.*');
    }
}
```

```blade
@if($this->permCan('posts.*'))
    <button wire:click="create">New</button>
@endif
```

### Cache Management

```bash
php artisan permission:flush
```

Clears both Spatie's permission cache and the wildcard pattern cache.

## Documentation

- [Installation](docs/installation.md)
- [Wildcard Permissions](docs/wildcard-permissions.md)
- [Blade Directives](docs/blade-directives.md)
- [Middleware](docs/middleware.md)
- [Livewire](docs/livewire.md)
- [Reactive Updates](docs/reactivity.md)
- [Configuration](docs/configuration.md)

## Testing

```bash
composer test
```

## License

MIT
