<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Super-Admin Role
    |--------------------------------------------------------------------------
    |
    | When set, a Gate::before rule is registered that grants all permissions
    | to users with this role. Set to null to disable.
    |
    | Works with Laravel's native @can / Gate::allows / $user->can().
    |
    */
    'super_admin_role' => env('PERMISSION_SUPER_ADMIN_ROLE', 'super-admin'),

    /*
    |--------------------------------------------------------------------------
    | Global Team Id
    |--------------------------------------------------------------------------
    |
    | With Spatie's teams on, every role assignment belongs to a team, and the
    | team column is part of the pivot's primary key, so it cannot be null.
    | Roles that must count in every team — the super-admin above all — are
    | stored under this reserved id by assignGlobalRole(). It must never be the
    | id of a real team: 0 is safe for auto-increment ids; use another value for
    | UUID teams. With teams off it is not used.
    |
    | The super-admin gate only honours a global assignment when teams are on.
    |
    */

    'global_team_id' => env('PERMISSION_GLOBAL_TEAM_ID', 0),

    /*
    |--------------------------------------------------------------------------
    | Register Spatie Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | Automatically register Spatie's middleware aliases (role, permission,
    | role_or_permission) so you don't need to add them manually in
    | bootstrap/app.php.
    |
    */
    'register_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Wildcard Results
    |--------------------------------------------------------------------------
    |
    | Cache resolved wildcard pattern results within a single request.
    |
    */
    'cache_per_request' => true,

    /*
    |--------------------------------------------------------------------------
    | Blade Directive Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for the Blade directives registered by this package.
    | Leave empty for @canPermission, @hasRoleOrPermission, etc.
    | Set to e.g. "pex" to get @pexCanPermission, etc.
    |
    */
    'blade_prefix' => '',

    /*
    |--------------------------------------------------------------------------
    | Livewire Auto-Refresh
    |--------------------------------------------------------------------------
    |
    | Flush the wildcard cache on every Livewire hydrate so permission
    | checks always use fresh data.
    |
    */
    'livewire_auto_refresh' => true,

    /*
    |--------------------------------------------------------------------------
    | Broadcast Permission Changes
    |--------------------------------------------------------------------------
    |
    | Dispatch a PermissionChanged broadcast event on every permission/role
    | mutation. Requires Echo + a queue worker.
    |
    */
    'broadcast_changes' => env('PERMISSION_BROADCAST_CHANGES', false),

];
