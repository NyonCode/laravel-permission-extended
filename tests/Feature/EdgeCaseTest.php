<?php

declare(strict_types=1);

use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// -----------------------------------------------------------------
// Edge cases that break in production
// -----------------------------------------------------------------

describe('edge cases', function () {
    beforeEach(function () {
        permissions(['admin.create', 'admin.delete', 'posts.edit']);
        $this->user = testUser('edge@example.com');
    });

    test('hasPermissionTo with Permission object (not string)', function () {
        $perm = Permission::findByName('posts.edit', 'web');
        $this->user->givePermissionTo($perm);

        expect($this->user->hasPermissionTo($perm))->toBeTrue();
    });

    test('hasPermissionTo with nonexistent exact permission throws', function () {
        expect(fn () => $this->user->hasPermissionTo('nonexistent'))
            ->toThrow(PermissionDoesNotExist::class);
    });

    test('wildcard on user with zero permissions returns false', function () {
        expect($this->user->hasPermissionTo('admin.*'))->toBeFalse()
            ->and($this->user->hasAnyPermission(['admin.*']))->toBeFalse();
    });

    test('hasAllPermissions with empty array returns true', function () {
        // No requirements = all met
        expect($this->user->hasAllPermissions([]))->toBeTrue();
    });

    test('hasAnyPermission with empty array returns false', function () {
        expect($this->user->hasAnyPermission([]))->toBeFalse();
    });

    test('hasRoleOrPermission with pipe string containing spaces', function () {
        $this->user->givePermissionTo('admin.create');

        expect($this->user->hasRoleOrPermission(' admin.* | posts.* '))->toBeTrue();
    });

    test('wildcard does not match across different prefixes', function () {
        $this->user->givePermissionTo('admin.create');

        expect($this->user->hasPermissionTo('posts.*'))->toBeFalse();
    });

    test('flushWildcardCache returns $this for chaining', function () {
        $result = $this->user->flushWildcardCache();

        expect($result)->toBe($this->user);
    });

    test('givePermissionTo returns $this for chaining', function () {
        $result = $this->user->givePermissionTo('admin.create');

        expect($result)->toBe($this->user);
    });
});

// -----------------------------------------------------------------
// Many permissions (performance sanity check)
// -----------------------------------------------------------------

describe('many permissions', function () {
    test('wildcard works with 100+ permissions', function () {
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = "module{$i}.action".($i % 5);
        }
        permissions($names);

        $user = testUser('perf@example.com');
        $user->givePermissionTo(array_slice($names, 0, 50));

        // Should not timeout
        expect($user->hasPermissionTo('module1.*'))->toBeTrue()
            ->and($user->hasPermissionTo('module99.*'))->toBeFalse()
            ->and($user->hasAnyPermission(['module0.*', 'module99.*']))->toBeTrue();
    });
});

// -----------------------------------------------------------------
// Multiple roles with overlapping permissions
// -----------------------------------------------------------------

describe('overlapping roles', function () {
    test('wildcard deduplicates across roles', function () {
        permissions(['shared.read', 'shared.write', 'admin.create']);

        $role1 = Role::create(['name' => 'reader', 'guard_name' => 'web']);
        $role1->givePermissionTo('shared.read');

        $role2 = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $role2->givePermissionTo(['shared.read', 'shared.write']);

        $user = testUser('overlap@example.com');
        $user->assignRole([$role1, $role2]);

        // shared.* should find both shared.read and shared.write
        $matching = $user->getWildcardPermissions('shared.*');
        expect($matching)->toHaveCount(2);
    });
});

// -----------------------------------------------------------------
// Wildcard pattern edge cases
// -----------------------------------------------------------------

describe('pattern edge cases', function () {
    test('double dot in permission name', function () {
        Permission::findOrCreate('api..v2.users', 'web');
        $user = testUser('ddot@example.com');
        $user->givePermissionTo('api..v2.users');

        expect($user->hasPermissionTo('api.*'))->toBeTrue();
    });

    test('permission name with numbers', function () {
        Permission::findOrCreate('module123.action456', 'web');
        $user = testUser('num@example.com');
        $user->givePermissionTo('module123.action456');

        expect($user->hasPermissionTo('module123.*'))->toBeTrue()
            ->and($user->hasPermissionTo('module12.*'))->toBeFalse();
    });

    test('permission name with hyphens', function () {
        Permission::findOrCreate('user-management.create', 'web');
        $user = testUser('hyph@example.com');
        $user->givePermissionTo('user-management.create');

        expect($user->hasPermissionTo('user-management.*'))->toBeTrue();
    });
});
