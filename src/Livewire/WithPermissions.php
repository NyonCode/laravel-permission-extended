<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Livewire;

use Livewire\Attributes\On;
use NyonCode\PermissionExtended\WildcardChecker;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

/**
 * Livewire 3 trait for wildcard permission checks.
 *
 * All public methods use the "perm" prefix so they never collide with
 * Component::can() or AuthorizesRequests::authorize().
 *
 * Uses #[On] attributes instead of getListeners() to avoid conflicts
 * with user-defined listeners in the component.
 */
trait WithPermissions
{
    /**
     * Currently authenticated user ID.
     */
    public ?int $permUserId = null;

    /**
     * Snapshot of wildcard permission names to detect changes.
     *
     * @var array<int,string>|null
     */
    protected ?array $permSnapshot = null;

    // =================================================================
    // Authorisation (abort 403)
    // =================================================================

    /**
     * Authorize a single permission or abort with 403.
     */
    public function permAuthorize(string $permission, ?string $guard = null): void
    {
        if (! $this->permCan($permission, $guard)) {
            abort(403, "Unauthorized: [{$permission}].");
        }
    }

    /**
     * Authorize if the user has any of the given permissions.
     *
     * @param  array<int,string>  $permissions
     */
    public function permAuthorizeAny(array $permissions, ?string $guard = null): void
    {
        if (! $this->permCanAny($permissions, $guard)) {
            abort(403);
        }
    }

    /**
     * Authorize if the user has all of the given permissions.
     *
     * @param  array<int,string>  $permissions
     */
    public function permAuthorizeAll(array $permissions, ?string $guard = null): void
    {
        if (! $this->permCanAll($permissions, $guard)) {
            abort(403);
        }
    }

    /**
     * Authorize if the user has a role or permission.
     *
     * @param  string|array<int,string>  $items
     */
    public function permAuthorizeRoleOr(string|array $items, ?string $guard = null): void
    {
        if (! $this->permCanRoleOr($items, $guard)) {
            abort(403);
        }
    }

    // =================================================================
    // Boolean checks (safe for Blade)
    // =================================================================

    /**
     * Check if the user has a permission.
     */
    public function permCan(string $permission, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        if (! $user) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission, $guard);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * Check if the user has any of the given permissions.
     *
     * @param  array<int,string>  $permissions
     */
    public function permCanAny(array $permissions, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasAnyPermission($permissions);
    }

    /**
     * Check if the user has all of the given permissions.
     *
     * @param  array<int,string>  $permissions
     */
    public function permCanAll(array $permissions, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasAllPermissions($permissions);
    }

    /**
     * Check if the user has a role or permission.
     *
     * @param  string|array<int,string>  $items
     */
    public function permCanRoleOr(string|array $items, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasRoleOrPermission($items, $guard);
    }

    /**
     * Return permission names matching a wildcard pattern.
     *
     * @return array<int,string>
     */
    public function permMatching(string $pattern, ?string $guard = null): array
    {
        $user = $this->permUser($guard);

        if (! $user || ! method_exists($user, 'wildcardPermissionNames')) {
            return [];
        }

        return WildcardChecker::filter($pattern, $user->wildcardPermissionNames($guard))->all();
    }

    // =================================================================
    // Reactivity — uses #[On] attribute (Livewire 3 native)
    // =================================================================

    /**
     * Refresh permission caches when permissions change.
     *
     * Handles both client-side dispatches and Echo broadcast events.
     * The Echo listener is registered dynamically in bootWithPermissions().
     */
    #[On('permissions-changed')]
    public function onPermissionsChanged(): void
    {
        $this->permRefresh();
    }

    /**
     * Detect whether the permission snapshot has changed.
     */
    public function permissionsChanged(): bool
    {
        $this->permRefresh();

        $user = $this->permUser();

        if (! $user || ! method_exists($user, 'wildcardPermissionNames')) {
            return false;
        }

        $current = $user->wildcardPermissionNames()->sort()->values()->all();
        $changed = $this->permSnapshot !== $current;

        if ($changed) {
            $this->permSnapshot = $current;
        }

        return $changed;
    }

    // =================================================================
    // Lifecycle hooks
    // =================================================================

    /**
     * Livewire boot hook to register dynamic Echo listeners.
     *
     * Uses Livewire 3's internal $listeners property to add the Echo
     * channel listener without overriding getListeners(). This avoids
     * conflicts with user-defined listeners or other traits.
     */
    public function bootWithPermissions(): void
    {
        if (
            config('permission-extended.broadcast_changes', false)
            && $this->permUserId
            && property_exists($this, 'listeners')
        ) {
            $this->listeners["echo-private:permissions.{$this->permUserId},PermissionChanged"] = 'onPermissionsChanged';
        }
    }

    /**
     * Livewire mount hook to initialize permission state.
     */
    public function mountWithPermissions(): void
    {
        $user = $this->permUser();

        if ($user) {
            $this->permUserId = $user->getKey();
        }

        $this->permTakeSnapshot();
    }

    /**
     * Livewire hydrate hook to refresh cached permissions.
     */
    public function hydrateWithPermissions(): void
    {
        if (config('permission-extended.livewire_auto_refresh', true)) {
            WildcardChecker::flush();

            $user = $this->permUser();

            if ($user && method_exists($user, 'flushWildcardCache')) {
                $user->flushWildcardCache();
            }
        }
    }

    // =================================================================
    // Internal
    // =================================================================

    /**
     * Get the authenticated user for a guard.
     */
    protected function permUser(?string $guard = null): mixed
    {
        return auth()->guard($guard)->user();
    }

    /**
     * Refresh permission caches for the current user.
     */
    protected function permRefresh(): void
    {
        $user = $this->permUser();

        if (! $user) {
            return;
        }

        if (method_exists($user, 'flushWildcardCache')) {
            $user->flushWildcardCache();
        }

        $user->unsetRelation('permissions');
        $user->unsetRelation('roles');

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Capture the current permission snapshot.
     */
    protected function permTakeSnapshot(): void
    {
        $user = $this->permUser();

        if ($user && method_exists($user, 'wildcardPermissionNames')) {
            $this->permSnapshot = $user->wildcardPermissionNames()->sort()->values()->all();
        }
    }
}
