<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Blade;

use Illuminate\Support\Facades\Blade;

final class Directives
{
    public static function register(): void
    {
        $p = config('permission-extended.blade_prefix', '');

        Blade::if($p.'canPermission', fn (string $perm, ?string $guard = null) => self::check(fn ($u) => $u->hasPermissionTo($perm, $guard)));

        Blade::if($p.'canAnyPermission', fn (array $perms, ?string $guard = null) => self::check(fn ($u) => $u->hasAnyPermission($perms)));

        Blade::if($p.'canAllPermissions', fn (array $perms, ?string $guard = null) => self::check(fn ($u) => $u->hasAllPermissions($perms)));

        Blade::if($p.'hasRoleOrPermission', fn (string|array $items, ?string $guard = null) => self::check(fn ($u) => $u->hasRoleOrPermission($items, $guard)));

        Blade::if($p.'unlessCanPermission', fn (string $perm, ?string $guard = null) => ! self::check(fn ($u) => $u->hasPermissionTo($perm, $guard)));
    }

    private static function check(\Closure $callback): bool
    {
        $user = auth()->user();

        return $user !== null && $callback($user);
    }
}
