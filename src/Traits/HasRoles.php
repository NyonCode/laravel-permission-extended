<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Traits;

use Closure;
use Illuminate\Support\Collection;
use NyonCode\PermissionExtended\Events\PermissionChanged;
use NyonCode\PermissionExtended\Events\PermissionChangedQuiet;
use NyonCode\PermissionExtended\WildcardChecker;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

/**
 * Drop-in replacement for Spatie's HasRoles.
 *
 * Usage — use this trait INSTEAD of HasRoles:
 *
 *     class User extends Authenticatable
 *     {
 *         use \NyonCode\PermissionExtended\Traits\HasRoles;
 *     }
 *
 * Every Spatie method keeps working. The four methods below now also
 * accept wildcard patterns (e.g. "admin.*").
 *
 * With Spatie's teams on, a role is assigned *in a team* and only counts while
 * that team is the current one. The global-role methods are for the roles that
 * must count everywhere — above all the super-admin — and store the assignment
 * under a reserved team id (`permission-extended.global_team_id`), because the
 * team column on Spatie's pivot is part of its primary key and cannot be null.
 * Without teams they are the plain role methods.
 */
trait HasRoles
{
    // Import Spatie's trait and alias the methods we override.
    use SpatieHasRoles {
        SpatieHasRoles::hasPermissionTo as protected spatieHasPermissionTo;
        SpatieHasRoles::hasAnyPermission as protected spatieHasAnyPermission;
        SpatieHasRoles::hasAllPermissions as protected spatieHasAllPermissions;
        SpatieHasRoles::givePermissionTo as protected spatieGivePermissionTo;
        SpatieHasRoles::revokePermissionTo as protected spatieRevokePermissionTo;
        SpatieHasRoles::syncPermissions as protected spatieSyncPermissions;
        SpatieHasRoles::assignRole as protected spatieAssignRole;
        SpatieHasRoles::removeRole as protected spatieRemoveRole;
        SpatieHasRoles::syncRoles as protected spatieSyncRoles;
    }

    /**
     * Cached list of permission names for wildcard matching.
     *
     * @var Collection<int,string>|null
     */
    protected ?Collection $wildcardPermissionNamesCache = null;

    /**
     * Global role names, as `name|guard` keys, read once per model instance.
     *
     * The super-admin gate asks on every ability check, and a page of a table
     * makes dozens of them.
     *
     * @var Collection<int,string>|null
     */
    protected ?Collection $globalRoleKeysCache = null;

    // =================================================================
    // Permission checks (with wildcard support)
    // =================================================================

    /**
     * Determine if the user has the given permission or wildcard match.
     */
    public function hasPermissionTo(Permission|string $permission, ?string $guardName = null): bool
    {
        if (is_string($permission) && str_contains($permission, '*')) {
            return $this->matchesWildcard($permission, $guardName);
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /**
     * Determine if the user has any of the given permissions.
     *
     * @param  string|Permission  ...$permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        foreach (collect($permissions)->flatten() as $permission) {
            if (is_string($permission) && str_contains($permission, '*')) {
                if ($this->matchesWildcard($permission)) {
                    return true;
                }

                continue;
            }

            try {
                if ($this->spatieHasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given permissions.
     *
     * @param  string|Permission  ...$permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        foreach (collect($permissions)->flatten() as $permission) {
            if (is_string($permission) && str_contains($permission, '*')) {
                if (! $this->matchesWildcard($permission)) {
                    return false;
                }

                continue;
            }

            try {
                if (! $this->spatieHasPermissionTo($permission)) {
                    return false;
                }
            } catch (PermissionDoesNotExist) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user has a role or a permission.
     *
     * @param  string|int|Role|Permission|array<int,string|int|Role|Permission>|Collection<int,string|int|Role|Permission>  $rolesOrPermissions
     */
    public function hasRoleOrPermission($rolesOrPermissions, ?string $guardName = null): bool
    {
        foreach ($this->normaliseItems($rolesOrPermissions) as $item) {
            if ($this->hasRole($item, $guardName)) {
                return true;
            }

            if (is_string($item) && str_contains($item, '*')) {
                if ($this->matchesWildcard($item, $guardName)) {
                    return true;
                }

                continue;
            }

            try {
                if ($this->spatieHasPermissionTo($item, $guardName)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return false;
    }

    // =================================================================
    // Mutation methods (auto-flush cache + fire events)
    // =================================================================

    /**
     * Give the user the given permissions and flush caches.
     *
     * @param  mixed  ...$permissions
     */
    public function givePermissionTo(...$permissions): static
    {
        $this->spatieGivePermissionTo(...$permissions);
        $this->flushWildcardCache();
        $this->firePermissionEvent('give', $permissions);

        return $this;
    }

    /**
     * Revoke a permission and flush caches.
     */
    public function revokePermissionTo(mixed $permission): static
    {
        $this->spatieRevokePermissionTo($permission);
        $this->flushWildcardCache();
        $this->firePermissionEvent('revoke', [$permission]);

        return $this;
    }

    /**
     * Sync permissions and flush caches.
     *
     * @param  mixed  ...$permissions
     */
    public function syncPermissions(...$permissions): static
    {
        $this->spatieSyncPermissions(...$permissions);
        $this->flushWildcardCache();
        $this->firePermissionEvent('sync_permissions', $permissions);

        return $this;
    }

    /**
     * Assign roles and flush caches.
     *
     * @param  mixed  ...$roles
     */
    public function assignRole(...$roles): static
    {
        $this->spatieAssignRole(...$roles);
        $this->flushWildcardCache();
        $this->firePermissionEvent('assign_role', $roles);

        return $this;
    }

    /**
     * Remove a role and flush caches.
     */
    public function removeRole(mixed $role): static
    {
        $this->spatieRemoveRole($role);
        $this->flushWildcardCache();
        $this->firePermissionEvent('remove_role', [$role]);

        return $this;
    }

    /**
     * Sync roles and flush caches.
     *
     * @param  mixed  ...$roles
     */
    public function syncRoles(...$roles): static
    {
        $this->spatieSyncRoles(...$roles);
        $this->flushWildcardCache();
        $this->firePermissionEvent('sync_roles', $roles);

        return $this;
    }

    // =================================================================
    // Global roles (count in every team)
    // =================================================================

    /**
     * Assign roles that count in every team, not just the current one.
     *
     * The roles are looked up as global roles — ones that belong to no team —
     * so a role a team owns cannot be made global by accident.
     *
     * @param  mixed  ...$roles
     */
    public function assignGlobalRole(...$roles): static
    {
        $this->withinGlobalScope(fn () => $this->spatieAssignRole(...$roles));
        $this->flushWildcardCache();
        $this->firePermissionEvent('assign_global_role', $roles);

        return $this;
    }

    /**
     * Remove a role assigned with {@see assignGlobalRole()}.
     */
    public function removeGlobalRole(mixed $role): static
    {
        $this->withinGlobalScope(fn () => $this->spatieRemoveRole($role));
        $this->flushWildcardCache();
        $this->firePermissionEvent('remove_global_role', [$role]);

        return $this;
    }

    /**
     * Whether the model has any of the given roles globally.
     *
     * Independent of the current team: this is the question the super-admin
     * gate asks, and a super-admin assigned in one team only is not one.
     *
     * @param  string|Role|array<int,string|Role>  $roles
     */
    public function hasGlobalRole(string|Role|array $roles, ?string $guardName = null): bool
    {
        if (! app(PermissionRegistrar::class)->teams) {
            return $this->hasRole($roles, $guardName);
        }

        $keys = $this->globalRoleKeys();

        foreach (is_array($roles) ? $roles : [$roles] as $role) {
            $name = $role instanceof Role ? $role->name : $role;
            $guard = $role instanceof Role ? $role->guard_name : $guardName;

            $matches = $guard === null
                ? $keys->contains(fn (string $key): bool => str_starts_with($key, $name.'|'))
                : $keys->contains($name.'|'.$guard);

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * The reserved team id global assignments are stored under.
     */
    public static function globalTeamId(): int|string
    {
        $id = config('permission-extended.global_team_id', 0);

        return is_int($id) || is_string($id) ? $id : 0;
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Get all Permission models whose name matches a wildcard pattern.
     *
     * @return Collection<int,Permission>
     */
    public function getWildcardPermissions(string $pattern, ?string $guardName = null): Collection
    {
        $matching = WildcardChecker::filter($pattern, $this->wildcardPermissionNames($guardName));

        return $this->getAllPermissions()
            ->filter(fn ($p) => $matching->contains($p->name))
            ->values();
    }

    /**
     * All permission-name strings this model currently has.
     *
     * @return Collection<int,string>
     */
    public function wildcardPermissionNames(?string $guardName = null): Collection
    {
        $this->wildcardPermissionNamesCache ??= $this->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->values();

        if ($guardName !== null) {
            return $this->getAllPermissions()
                ->where('guard_name', $guardName)
                ->pluck('name')
                ->unique()
                ->values();
        }

        return $this->wildcardPermissionNamesCache;
    }

    /**
     * Clear wildcard caches.
     */
    public function flushWildcardCache(): static
    {
        $this->wildcardPermissionNamesCache = null;
        $this->globalRoleKeysCache = null;
        WildcardChecker::flush();

        return $this;
    }

    // =================================================================
    // Internal
    // =================================================================

    /**
     * Check whether the pattern matches any of the user's permissions.
     */
    protected function matchesWildcard(string $pattern, ?string $guardName = null): bool
    {
        return WildcardChecker::filter($pattern, $this->wildcardPermissionNames($guardName))
            ->isNotEmpty();
    }

    /**
     * Run a callback with the global team as the current one, then put it back.
     *
     * The loaded `roles` relation is dropped on both sides: Spatie reads it for
     * the team that was current when it loaded, and a stale one would make the
     * callback — or the code after it — answer about the wrong team.
     */
    protected function withinGlobalScope(Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return $callback();
        }

        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(static::globalTeamId());
        $this->unsetRelation('roles');

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $this->unsetRelation('roles');
        }
    }

    /**
     * The model's global role assignments, read straight off the pivot.
     *
     * Only roles that belong to no team (or to the reserved global one) count,
     * which is what {@see assignGlobalRole()} can store.
     *
     * @return Collection<int,string>
     */
    protected function globalRoleKeys(): Collection
    {
        if ($this->globalRoleKeysCache !== null) {
            return $this->globalRoleKeysCache;
        }

        $registrar = app(PermissionRegistrar::class);
        $roleClass = $this->getRoleClass();
        $role = new $roleClass;
        $pivot = (string) config('permission.table_names.model_has_roles');
        $morphKey = (string) config('permission.column_names.model_morph_key');
        $teamOnRole = $role->getTable().'.'.$registrar->teamsKey;

        return $this->globalRoleKeysCache = $roleClass::query()
            ->join($pivot, $pivot.'.'.$registrar->pivotRole, '=', $role->getQualifiedKeyName())
            ->where($pivot.'.'.$morphKey, $this->getKey())
            ->where($pivot.'.model_type', $this->getMorphClass())
            ->where($pivot.'.'.$registrar->teamsKey, static::globalTeamId())
            ->where(fn ($q) => $q->whereNull($teamOnRole)->orWhere($teamOnRole, static::globalTeamId()))
            ->get([$role->getTable().'.name', $role->getTable().'.guard_name'])
            ->map(fn ($r): string => $r->name.'|'.$r->guard_name)
            ->values();
    }

    /**
     * Normalize roles or permissions input into an array.
     *
     * @return array<int,mixed>
     */
    protected function normaliseItems(mixed $items): array
    {
        if (is_string($items)) {
            return array_map('trim', explode('|', $items));
        }

        if ($items instanceof Collection) {
            return $items->all();
        }

        return is_array($items) ? $items : [$items];
    }

    /**
     * Dispatch permission change events.
     *
     * @param  array<int,mixed>  $payload
     */
    protected function firePermissionEvent(string $action, array $payload): void
    {
        $quiet = PermissionChangedQuiet::class;

        if (class_exists($quiet)) {
            $quiet::dispatch($this, ['action' => $action, 'payload' => $payload]);
        }

        if (config('permission-extended.broadcast_changes', false)) {
            $broadcast = PermissionChanged::class;

            if (class_exists($broadcast)) {
                $broadcast::dispatch($this, ['action' => $action, 'payload' => $payload]);
            }
        }
    }
}
