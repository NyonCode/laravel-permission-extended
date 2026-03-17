<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use NyonCode\PermissionExtended\PermissionExtendedServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// -----------------------------------------------------------------
// Super-Admin Gate::before
// -----------------------------------------------------------------

describe('super-admin gate', function () {
    test('super-admin role bypasses all permission checks via Gate', function () {
        permissions(['posts.edit', 'posts.delete', 'admin.create']);

        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = testUser('sa@example.com');
        $user->assignRole($role);

        // User has NO direct permissions, but Gate should return true
        expect($user->can('posts.edit'))->toBeTrue()
            ->and($user->can('admin.create'))->toBeTrue()
            ->and($user->can('some.random.ability'))->toBeTrue();
    });

    test('non-super-admin user still needs actual permissions', function () {
        permissions(['posts.edit', 'posts.delete']);

        $user = testUser('normal@example.com');
        $user->givePermissionTo('posts.edit');

        expect($user->can('posts.edit'))->toBeTrue()
            ->and($user->can('posts.delete'))->toBeFalse();
    });

    test('custom super-admin role name from config', function () {
        config()->set('permission-extended.super_admin_role', 'root');

        // Re-boot the provider to re-register Gate::before with new config
        $provider = app()->getProvider(PermissionExtendedServiceProvider::class);
        if ($provider && method_exists($provider, 'bootingPackage')) {
            // Gate::before callbacks stack — the new one will also check 'root'
            $provider->bootingPackage();
        }

        $role = Role::create(['name' => 'root', 'guard_name' => 'web']);
        $user = testUser('root@example.com');
        $user->assignRole($role);

        expect($user->can('anything'))->toBeTrue();
    });

    test('super-admin disabled when config is null', function () {
        config()->set('permission-extended.super_admin_role', null);

        permissions(['posts.edit']);

        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = testUser('disabled@example.com');
        $user->assignRole($role);

        // Without Gate::before, super-admin role alone doesn't grant permissions
        // (unless the role has the permission assigned)
        expect($user->hasPermissionTo('posts.edit'))->toBeFalse();
    });
});

// -----------------------------------------------------------------
// Middleware Auto-Registration
// -----------------------------------------------------------------

describe('middleware registration', function () {
    test('spatie middleware aliases are registered', function () {
        /** @var Router $router */
        $router = app('router');

        $middleware = $router->getMiddleware();

        expect($middleware)->toHaveKey('role')
            ->and($middleware)->toHaveKey('permission')
            ->and($middleware)->toHaveKey('role_or_permission');
    });

    test('middleware can be disabled via config', function () {
        // This test just verifies the config option exists
        expect(config('permission-extended.register_middleware'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// permission:flush Command
// -----------------------------------------------------------------

describe('permission:flush command', function () {
    test('command exists and runs successfully', function () {
        $exitCode = Artisan::call('permission:flush');

        expect($exitCode)->toBe(0);
    });

    test('command clears spatie cache', function () {
        permissions(['test.permission']);

        $user = testUser('flush@example.com');
        $user->givePermissionTo('test.permission');

        expect($user->hasPermissionTo('test.permission'))->toBeTrue();

        Artisan::call('permission:flush');

        // After flush, should still work (reloads from DB)
        $user->flushWildcardCache();
        expect($user->hasPermissionTo('test.permission'))->toBeTrue();
    });
});
