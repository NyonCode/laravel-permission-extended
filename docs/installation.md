# Installation

```bash
composer require nyoncode/laravel-permission-extended
php artisan permission-extended:install
```

The install command will:
1. Publish `config/permission-extended.php`
2. Publish Spatie's permission migration tables
3. Ask to run migrations
4. Optionally register the service provider

### Manual Setup

Change the `HasRoles` import — from Spatie to this package:

```php
// Before:  use Spatie\Permission\Traits\HasRoles;
// After:
use NyonCode\PermissionExtended\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Do not use both Spatie's and this package's `HasRoles` on the same model.

### Verify

```php
$user = User::first();
$user->givePermissionTo('admin.create');
$user->hasPermissionTo('admin.*'); // true
$user->can('admin.*');             // true (via Gate)
```
