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

Replace `HasRoles` with `HasWildcardPermissions`:

```php
use NyonCode\PermissionExtended\Traits\HasWildcardPermissions;

class User extends Authenticatable
{
    use HasWildcardPermissions;
}
```

Do not use both traits — `HasWildcardPermissions` includes `HasRoles` internally.

### Verify

```php
$user = User::first();
$user->givePermissionTo('admin.create');
$user->hasPermissionTo('admin.*'); // true
$user->can('admin.*');             // true (via Gate)
```
