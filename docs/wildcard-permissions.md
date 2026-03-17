# Wildcard Permissions

| Pattern | Matches | Does not match |
|---------|---------|----------------|
| `admin.*` | `admin.create`, `admin.users.delete` | `posts.edit` |
| `*.create` | `admin.create`, `posts.create` | `admin.delete` |
| `*` | everything | — |

```php
$user->hasPermissionTo('admin.*');
$user->hasAnyPermission(['admin.*', 'posts.edit']);
$user->hasAllPermissions(['admin.*', 'posts.*']);
$user->hasRoleOrPermission('editor|admin.*');
$user->getWildcardPermissions('admin.*');     // Collection<Permission>
$user->wildcardPermissionNames();             // Collection<string>
```

### Super-Admin

Users with the configured super-admin role pass all Gate checks:

```php
// config: 'super_admin_role' => 'super-admin'
$user->assignRole('super-admin');
$user->can('anything.at.all'); // true — via Gate::before
```

### Cache

```bash
php artisan permission:flush   # clear Spatie + wildcard cache
```

Or in code: `$user->flushWildcardCache()`
