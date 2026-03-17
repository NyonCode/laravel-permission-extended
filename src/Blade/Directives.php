<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Blade;

use Closure;
use Illuminate\Support\Facades\Blade;

/**
 * Registers custom Blade directives for permission checks.
 */
final class Directives
{
    /**
     * Register all custom Blade directives.
     *
     * @return void
     */
    public static function register(): void
    {
        $p = config('permission-extended.blade_prefix', '');

        Blade::if($p.'canPermission', fn (string $perm, ?string $guard = null) => self::check(fn ($u) => $u->hasPermissionTo($perm, $guard)));

        Blade::if($p.'canAnyPermission', fn (array $perms, ?string $guard = null) => self::check(fn ($u) => $u->hasAnyPermission($perms)));

        Blade::if($p.'canAllPermissions', fn (array $perms, ?string $guard = null) => self::check(fn ($u) => $u->hasAllPermissions($perms)));

        Blade::if($p.'hasRoleOrPermission', fn (string|array $items, ?string $guard = null) => self::check(fn ($u) => $u->hasRoleOrPermission($items, $guard)));

        Blade::if($p.'unlessCanPermission', fn (string $perm, ?string $guard = null) => ! self::check(fn ($u) => $u->hasPermissionTo($perm, $guard)));
    }

    /**
     * Evaluate the callback only when an authenticated user is present.
     *
     * @param Closure $callback
     * @return bool
     */
    private static function check(Closure $callback): bool
    {
        $user = auth()->guard()->user();

        return $user !== null && $callback($user);
    }
}
