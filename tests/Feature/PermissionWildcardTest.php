<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;

beforeEach(function () {
    permissions(['admin.create', 'admin.delete', 'admin.users.create', 'posts.edit', 'posts.delete', 'comments.view']);

    $this->user = testUser();
    $this->user->givePermissionTo(['admin.create', 'admin.delete', 'admin.users.create', 'posts.edit']);
});

// -----------------------------------------------------------------
// hasPermissionTo
// -----------------------------------------------------------------

describe('hasPermissionTo', function () {
    test('exact match', fn () => expect($this->user->hasPermissionTo('posts.edit'))->toBeTrue());
    test('exact miss', fn () => expect($this->user->hasPermissionTo('posts.delete'))->toBeFalse());
    test('wildcard match', fn () => expect($this->user->hasPermissionTo('admin.*'))->toBeTrue());
    test('wildcard miss', fn () => expect($this->user->hasPermissionTo('comments.*'))->toBeFalse());
    test('leading wildcard', fn () => expect($this->user->hasPermissionTo('*.create'))->toBeTrue());
});

// -----------------------------------------------------------------
// hasAnyPermission
// -----------------------------------------------------------------

describe('hasAnyPermission', function () {
    test('one matches', fn () => expect($this->user->hasAnyPermission(['comments.*', 'admin.*']))->toBeTrue());
    test('none match', fn () => expect($this->user->hasAnyPermission(['comments.*', 'settings.*']))->toBeFalse());
    test('mixed types', fn () => expect($this->user->hasAnyPermission(['settings.x', 'admin.*']))->toBeTrue());
});

// -----------------------------------------------------------------
// hasAllPermissions
// -----------------------------------------------------------------

describe('hasAllPermissions', function () {
    test('all match', fn () => expect($this->user->hasAllPermissions(['admin.*', 'posts.edit']))->toBeTrue());
    test('one fails', fn () => expect($this->user->hasAllPermissions(['admin.*', 'comments.*']))->toBeFalse());
});

// -----------------------------------------------------------------
// hasRoleOrPermission
// -----------------------------------------------------------------

describe('hasRoleOrPermission', function () {
    test('by role', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->user->assignRole('editor');
        expect($this->user->hasRoleOrPermission('editor|settings.*'))->toBeTrue();
    });

    test('by wildcard', fn () => expect($this->user->hasRoleOrPermission('manager|admin.*'))->toBeTrue());
    test('neither', fn () => expect($this->user->hasRoleOrPermission('manager|settings.*'))->toBeFalse());
    test('array', fn () => expect($this->user->hasRoleOrPermission(['nobody', 'admin.*']))->toBeTrue());
});

// -----------------------------------------------------------------
// getWildcardPermissions
// -----------------------------------------------------------------

describe('getWildcardPermissions', function () {
    test('returns models', function () {
        $perms = $this->user->getWildcardPermissions('admin.*');
        expect($perms)->toHaveCount(3)
            ->and($perms->pluck('name')->all())
            ->toContain('admin.create', 'admin.delete', 'admin.users.create');
    });
});

// -----------------------------------------------------------------
// Cache invalidation
// -----------------------------------------------------------------

describe('cache flush', function () {
    test('after give', function () {
        expect($this->user->hasPermissionTo('comments.*'))->toBeFalse();
        $this->user->givePermissionTo('comments.view');
        expect($this->user->hasPermissionTo('comments.*'))->toBeTrue();
    });

    test('after revoke', function () {
        $this->user->revokePermissionTo('admin.create');
        $this->user->revokePermissionTo('admin.delete');
        $this->user->revokePermissionTo('admin.users.create');
        expect($this->user->hasPermissionTo('admin.*'))->toBeFalse();
    });

    test('after sync', function () {
        $this->user->syncPermissions(['comments.view']);
        expect($this->user->hasPermissionTo('admin.*'))->toBeFalse()
            ->and($this->user->hasPermissionTo('comments.*'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Permissions via roles
// -----------------------------------------------------------------

describe('via roles', function () {
    test('wildcard on role permissions', function () {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['admin.create', 'admin.delete']);

        $u = testUser('role@example.com');
        $u->assignRole($role);

        expect($u->hasPermissionTo('admin.*'))->toBeTrue();
    });

    test('updates after removeRole', function () {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['admin.create']);

        $u = testUser('rm@example.com');
        $u->assignRole($role);
        expect($u->hasPermissionTo('admin.*'))->toBeTrue();

        $u->removeRole($role);
        expect($u->hasPermissionTo('admin.*'))->toBeFalse();
    });

    test('updates after syncRoles', function () {
        $a = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $a->givePermissionTo(['admin.create']);
        $e = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $e->givePermissionTo(['posts.edit']);

        $u = testUser('sync@example.com');
        $u->assignRole($a);
        $u->syncRoles([$e]);

        expect($u->hasPermissionTo('admin.*'))->toBeFalse()
            ->and($u->hasPermissionTo('posts.*'))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Spatie backward compat
// -----------------------------------------------------------------

describe('spatie compat', function () {
    test('hasRole', function () {
        Role::create(['name' => 'w', 'guard_name' => 'web']);
        $this->user->assignRole('w');
        expect($this->user->hasRole('w'))->toBeTrue();
    });

    test('can via gate', function () {
        expect($this->user->can('posts.edit'))->toBeTrue();
    });
});
