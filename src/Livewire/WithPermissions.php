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
 */
trait WithPermissions
{
    public ?int $permUserId = null;

    protected ?array $permSnapshot = null;

    // =================================================================
    // Authorisation (abort 403)
    // =================================================================

    public function permAuthorize(string $permission, ?string $guard = null): void
    {
        if (! $this->permCan($permission, $guard)) {
            abort(403, "Unauthorized: [{$permission}].");
        }
    }

    public function permAuthorizeAny(array $permissions, ?string $guard = null): void
    {
        if (! $this->permCanAny($permissions, $guard)) {
            abort(403);
        }
    }

    public function permAuthorizeAll(array $permissions, ?string $guard = null): void
    {
        if (! $this->permCanAll($permissions, $guard)) {
            abort(403);
        }
    }

    public function permAuthorizeRoleOr(string|array $items, ?string $guard = null): void
    {
        if (! $this->permCanRoleOr($items, $guard)) {
            abort(403);
        }
    }

    // =================================================================
    // Boolean checks (safe for Blade)
    // =================================================================

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

    public function permCanAny(array $permissions, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasAnyPermission($permissions);
    }

    public function permCanAll(array $permissions, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasAllPermissions($permissions);
    }

    public function permCanRoleOr(string|array $items, ?string $guard = null): bool
    {
        $user = $this->permUser($guard);

        return $user !== null && $user->hasRoleOrPermission($items, $guard);
    }

    /** @return string[] */
    public function permMatching(string $pattern, ?string $guard = null): array
    {
        $user = $this->permUser($guard);

        if (! $user || ! method_exists($user, 'wildcardPermissionNames')) {
            return [];
        }

        return WildcardChecker::filter($pattern, $user->wildcardPermissionNames($guard))->all();
    }

    // =================================================================
    // Reactivity
    // =================================================================

    #[On('permissions-changed')]
    public function onPermissionsChanged(): void
    {
        $this->permRefresh();
    }

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

    /** @return array<string,string> */
    public function getListeners(): array
    {
        $listeners = ['permissions-changed' => 'onPermissionsChanged'];

        if (config('permission-extended.broadcast_changes', false) && $this->permUserId) {
            $listeners["echo-private:permissions.{$this->permUserId},PermissionChanged"] = 'onPermissionsChanged';
        }

        return $listeners;
    }

    // =================================================================
    // Lifecycle hooks
    // =================================================================

    public function mountWithPermissions(): void
    {
        $user = $this->permUser();

        if ($user) {
            $this->permUserId = $user->getKey();
        }

        $this->permTakeSnapshot();
    }

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

    protected function permUser(?string $guard = null): mixed
    {
        return auth($guard)->user();
    }

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

    protected function permTakeSnapshot(): void
    {
        $user = $this->permUser();

        if ($user && method_exists($user, 'wildcardPermissionNames')) {
            $this->permSnapshot = $user->wildcardPermissionNames()->sort()->values()->all();
        }
    }
}
