# Changelog

## 1.0.0 - 2026-03-17

### Added
- `HasWildcardPermissions` trait — replaces `HasRoles`, transparent wildcard support in `hasPermissionTo`, `hasAnyPermission`, `hasAllPermissions`, `hasRoleOrPermission`
- `WildcardChecker` utility with per-request caching
- Super-admin Gate::before — configurable role that bypasses all permission checks
- Automatic Spatie middleware registration (role, permission, role_or_permission)
- Blade directives: `@canPermission`, `@canAnyPermission`, `@canAllPermissions`, `@hasRoleOrPermission`, `@unlessCanPermission`
- Livewire `WithPermissions` trait with `permAuthorize`, `permCan`, `permCanAny`, `permCanAll`, `permCanRoleOr`, `permMatching`, `permissionsChanged`
- `permission:flush` artisan command — clears Spatie + wildcard caches
- Install command via `php artisan permission-extended:install`
- `PermissionChanged` broadcast event (queued, opt-in) and `PermissionChangedQuiet` server-side event
- Auto cache flush on all permission/role mutations
- Broadcast channel `permissions.{userId}` auto-registered
- Built on `nyoncode/laravel-package-toolkit`
