<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// -----------------------------------------------------------------
// Install command registration
// -----------------------------------------------------------------

describe('install command', function () {
    test('is registered', function () {
        $commands = Artisan::all();

        expect($commands)->toHaveKey('permission-extended:install');
    });

    test('has --force option', function () {
        $command = Artisan::all()['permission-extended:install'];
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Spatie integration — tables exist after TestCase setUp
// -----------------------------------------------------------------

describe('spatie tables', function () {
    test('permission tables exist in test database', function () {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        expect($schema->hasTable('permissions'))->toBeTrue()
            ->and($schema->hasTable('roles'))->toBeTrue()
            ->and($schema->hasTable('model_has_permissions'))->toBeTrue()
            ->and($schema->hasTable('model_has_roles'))->toBeTrue()
            ->and($schema->hasTable('role_has_permissions'))->toBeTrue();
    });

    test('users table exists', function () {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        expect($schema->hasTable('users'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Full integration — from install to wildcard check
// -----------------------------------------------------------------

describe('end-to-end integration', function () {
    test('create permission, assign to user, check wildcard', function () {
        Permission::findOrCreate('articles.create', 'web');
        Permission::findOrCreate('articles.edit', 'web');
        Permission::findOrCreate('articles.delete', 'web');

        $user = testUser('integration@example.com');
        $user->givePermissionTo(['articles.create', 'articles.edit']);

        expect($user->hasPermissionTo('articles.*'))->toBeTrue()
            ->and($user->hasPermissionTo('articles.create'))->toBeTrue()
            ->and($user->hasPermissionTo('articles.delete'))->toBeFalse()
            ->and($user->can('articles.create'))->toBeTrue();
    });

    test('create role, assign permissions, verify via role', function () {
        Permission::findOrCreate('reports.view', 'web');
        Permission::findOrCreate('reports.export', 'web');

        $role = Role::create(['name' => 'analyst', 'guard_name' => 'web']);
        $role->givePermissionTo(['reports.view', 'reports.export']);

        $user = testUser('analyst@example.com');
        $user->assignRole('analyst');

        expect($user->hasPermissionTo('reports.*'))->toBeTrue()
            ->and($user->hasRole('analyst'))->toBeTrue()
            ->and($user->hasRoleOrPermission('analyst|settings.*'))->toBeTrue();
    });

    test('super-admin bypasses all checks via Gate', function () {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $user = testUser('superadmin@example.com');
        $user->assignRole('super-admin');

        expect($user->can('anything.at.all'))->toBeTrue()
            ->and($user->can('nonexistent.permission'))->toBeTrue();
    });

    test('revoke permission updates wildcard result', function () {
        Permission::findOrCreate('billing.read', 'web');
        Permission::findOrCreate('billing.write', 'web');

        $user = testUser('billing@example.com');
        $user->givePermissionTo(['billing.read', 'billing.write']);

        expect($user->hasPermissionTo('billing.*'))->toBeTrue();

        $user->revokePermissionTo('billing.read');
        $user->revokePermissionTo('billing.write');

        expect($user->hasPermissionTo('billing.*'))->toBeFalse();
    });

    test('sync permissions reflects in wildcard', function () {
        Permission::findOrCreate('shop.browse', 'web');
        Permission::findOrCreate('shop.purchase', 'web');
        Permission::findOrCreate('shop.refund', 'web');

        $user = testUser('shopper@example.com');
        $user->givePermissionTo(['shop.browse', 'shop.purchase', 'shop.refund']);

        expect($user->hasAllPermissions(['shop.*']))->toBeTrue();

        $user->syncPermissions(['shop.browse']);

        expect($user->hasPermissionTo('shop.browse'))->toBeTrue()
            ->and($user->hasPermissionTo('shop.purchase'))->toBeFalse()
            ->and($user->hasPermissionTo('shop.*'))->toBeTrue();
    });

    test('sync roles reflects in wildcard', function () {
        Permission::findOrCreate('cms.edit', 'web');
        Permission::findOrCreate('cms.publish', 'web');
        Permission::findOrCreate('api.read', 'web');

        $editor = Role::create(['name' => 'cms-editor', 'guard_name' => 'web']);
        $editor->givePermissionTo(['cms.edit', 'cms.publish']);

        $reader = Role::create(['name' => 'api-reader', 'guard_name' => 'web']);
        $reader->givePermissionTo(['api.read']);

        $user = testUser('roles@example.com');
        $user->assignRole('cms-editor');

        expect($user->hasPermissionTo('cms.*'))->toBeTrue();

        $user->syncRoles(['api-reader']);

        expect($user->hasPermissionTo('cms.*'))->toBeFalse()
            ->and($user->hasPermissionTo('api.*'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Middleware registration
// -----------------------------------------------------------------

describe('middleware integration', function () {
    test('spatie middleware aliases are available', function () {
        /** @var Router $router */
        $router = app('router');
        $middleware = $router->getMiddleware();

        expect($middleware)->toHaveKey('role')
            ->and($middleware)->toHaveKey('permission')
            ->and($middleware)->toHaveKey('role_or_permission');
    });
});

// -----------------------------------------------------------------
// Config integration
// -----------------------------------------------------------------

describe('config integration', function () {
    test('permission-extended config is loaded', function () {
        expect(config('permission-extended'))->toBeArray()
            ->and(config('permission-extended.super_admin_role'))->toBe('super-admin');
    });

    test('spatie permission config key exists', function () {
        expect(config('permission'))->toBeArray()
            ->and(config('permission.models.permission'))->not->toBeNull();
    });
});

// -----------------------------------------------------------------
// Flush command integration
// -----------------------------------------------------------------

describe('flush command integration', function () {
    test('flush clears cache without breaking subsequent checks', function () {
        permissions(['flush.test.read', 'flush.test.write']);

        $user = testUser('flush-int@example.com');
        $user->givePermissionTo(['flush.test.read', 'flush.test.write']);

        expect($user->hasPermissionTo('flush.test.*'))->toBeTrue();

        Artisan::call('permission:flush');
        $user->flushWildcardCache();

        // After flush, data reloads from DB — still works
        expect($user->hasPermissionTo('flush.test.*'))->toBeTrue()
            ->and($user->hasPermissionTo('flush.test.read'))->toBeTrue();
    });
});
