# Configuration

```php
// config/permission-extended.php
return [
    'super_admin_role'  => env('PERMISSION_SUPER_ADMIN_ROLE', 'super-admin'),
    'register_middleware' => true,
    'cache_per_request'   => true,
    'blade_prefix'        => '',
    'livewire_auto_refresh' => true,
    'broadcast_changes'   => env('PERMISSION_BROADCAST_CHANGES', false),
];
```

| Key | Default | Description |
|-----|---------|-------------|
| `super_admin_role` | `'super-admin'` | Role that bypasses all Gate checks. Set `null` to disable. |
| `register_middleware` | `true` | Auto-register Spatie middleware aliases (role, permission, role_or_permission). |
| `cache_per_request` | `true` | Cache wildcard regex results per request. |
| `blade_prefix` | `''` | Prefix for Blade directives. |
| `livewire_auto_refresh` | `true` | Flush cache on Livewire hydrate. |
| `broadcast_changes` | `false` | Dispatch broadcast events on permission changes. |
