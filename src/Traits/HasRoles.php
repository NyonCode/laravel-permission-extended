<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Traits;

use Illuminate\Support\Collection;
use NyonCode\PermissionExtended\Events\PermissionChanged;
use NyonCode\PermissionExtended\Events\PermissionChangedQuiet;
use NyonCode\PermissionExtended\WildcardChecker;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
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
