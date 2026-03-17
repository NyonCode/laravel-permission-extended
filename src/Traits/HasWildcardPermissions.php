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
use Spatie\Permission\Traits\HasRoles;

/**
 * Drop-in replacement for Spatie's HasRoles.
 *
 * Usage — use this trait INSTEAD of HasRoles:
 *
 *     class User extends Authenticatable
 *     {
 *         use \NyonCode\PermissionExtended\Traits\HasWildcardPermissions;
 *     }
 *
 * Every Spatie method keeps working. The four methods below now also
 * accept wildcard patterns (e.g. "admin.*").
 */
trait HasWildcardPermissions
{
    // Import Spatie's trait and alias the methods we override.
    use HasRoles {
        HasRoles::hasPermissionTo as protected spatieHasPermissionTo;
        HasRoles::hasAnyPermission as protected spatieHasAnyPermission;
        HasRoles::hasAllPermissions as protected spatieHasAllPermissions;
        HasRoles::givePermissionTo as protected spatieGivePermissionTo;
        HasRoles::revokePermissionTo as protected spatieRevokePermissionTo;
        HasRoles::syncPermissions as protected spatieSyncPermissions;
        HasRoles::assignRole as protected spatieAssignRole;
        HasRoles::removeRole as protected spatieRemoveRole;
        HasRoles::syncRoles as protected spatieSyncRoles;
    }

    protected ?Collection $wildcardPermissionNamesCache = null;

    // =================================================================
    // Permission checks (with wildcard support)
    // =================================================================

    /** @param  string|Permission  $permission */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (is_string($permission) && str_contains($permission, '*')) {
            return $this->matchesWildcard($permission, $guardName);
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /** @param  string|Permission  ...$permissions */
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

    /** @param  string|Permission  ...$permissions */
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
     * @param  string|int|array|Role|Permission|Collection  $rolesOrPermissions
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

    public function givePermissionTo(...$permissions): static
    {
        $this->spatieGivePermissionTo(...$permissions);
        $this->flushWildcardCache();
        $this->firePermissionEvent('give', $permissions);

        return $this;
    }

    public function revokePermissionTo($permission): static
    {
        $this->spatieRevokePermissionTo($permission);
        $this->flushWildcardCache();
        $this->firePermissionEvent('revoke', [$permission]);

        return $this;
    }

    public function syncPermissions(...$permissions): static
    {
        $this->spatieSyncPermissions(...$permissions);
        $this->flushWildcardCache();
        $this->firePermissionEvent('sync_permissions', $permissions);

        return $this;
    }

    public function assignRole(...$roles): static
    {
        $this->spatieAssignRole(...$roles);
        $this->flushWildcardCache();
        $this->firePermissionEvent('assign_role', $roles);

        return $this;
    }

    public function removeRole($role): static
    {
        $this->spatieRemoveRole($role);
        $this->flushWildcardCache();
        $this->firePermissionEvent('remove_role', [$role]);

        return $this;
    }

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

    /** Get all Permission models whose name matches a wildcard pattern. */
    public function getWildcardPermissions(string $pattern, ?string $guardName = null): Collection
    {
        $matching = WildcardChecker::filter($pattern, $this->wildcardPermissionNames($guardName));

        return $this->getAllPermissions()
            ->filter(fn ($p) => $matching->contains($p->name))
            ->values();
    }

    /** All permission-name strings this model currently has. */
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

    public function flushWildcardCache(): static
    {
        $this->wildcardPermissionNamesCache = null;
        WildcardChecker::flush();

        return $this;
    }

    // =================================================================
    // Internal
    // =================================================================

    protected function matchesWildcard(string $pattern, ?string $guardName = null): bool
    {
        return WildcardChecker::filter($pattern, $this->wildcardPermissionNames($guardName))
            ->isNotEmpty();
    }

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
