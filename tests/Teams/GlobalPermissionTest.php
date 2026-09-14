<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * An administrator across all teams: a global role with explicit permissions.
 *
 * Unlike the super-admin it bypasses nothing — it can do what its permissions
 * say, and taking one away takes it away — but it can do it in every team.
 * Spatie reads permissions through the current team's roles only, so without
 * this a globally assigned role's permissions counted nowhere.
 */

function atTeam(int|string|null $team): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($team);
}

beforeEach(function () {
    atTeam(null);
    permissions(['users.view', 'users.edit', 'posts.edit', 'billing.view']);

    Role::query()->create(['name' => 'admin', 'guard_name' => 'web']) // no team: global
        ->givePermissionTo(['users.view', 'users.edit']);
});

test('a global role\'s permissions count in every team, and outside any', function () {
    $user = testUser('admin@example.com');
    $user->assignGlobalRole('admin');

    foreach ([1, 2, null] as $team) {
        atTeam($team);
        $user = $user->fresh();

        expect($user->hasPermissionTo('users.edit'))->toBeTrue()
            ->and($user->can('users.view'))->toBeTrue()
            ->and($user->can('billing.view'))->toBeFalse();
    }
});

test('it is not a super-admin: it can do only what its permissions say', function () {
    $user = testUser('admin@example.com');
    $user->assignGlobalRole('admin');

    atTeam(1);

    expect($user->fresh()->can('posts.edit'))->toBeFalse()
        ->and($user->fresh()->can('anything.at.all'))->toBeFalse()
        ->and($user->fresh()->hasGlobalRole('super-admin'))->toBeFalse();
});

test('taking a permission from the role takes it from the administrator', function () {
    $user = testUser('admin@example.com');
    $user->assignGlobalRole('admin');

    Role::findByName('admin', 'web')->revokePermissionTo('users.edit');
    atTeam(1);

    expect($user->fresh()->hasPermissionTo('users.edit'))->toBeFalse()
        ->and($user->fresh()->hasPermissionTo('users.view'))->toBeTrue();
});

test('removing the global role takes its permissions away', function () {
    $user = testUser('admin@example.com');
    $user->assignGlobalRole('admin');
    $user->removeGlobalRole('admin');

    atTeam(1);

    expect($user->fresh()->can('users.view'))->toBeFalse();
});

test('global and team permissions add up', function () {
    $user = testUser('both@example.com');
    $user->assignGlobalRole('admin');

    atTeam(1);
    $editor = Role::create(['name' => 'editor', 'guard_name' => 'web', 'team_id' => 1]);
    $editor->givePermissionTo('posts.edit');
    $user->assignRole($editor);

    $inTeam1 = $user->fresh();

    expect($inTeam1->hasAllPermissions('users.edit', 'posts.edit'))->toBeTrue()
        ->and($inTeam1->hasAnyPermission('billing.view', 'users.view'))->toBeTrue()
        ->and($inTeam1->hasRoleOrPermission('users.view'))->toBeTrue();

    atTeam(2);
    $inTeam2 = $user->fresh();

    expect($inTeam2->hasPermissionTo('users.edit'))->toBeTrue()
        ->and($inTeam2->hasPermissionTo('posts.edit'))->toBeFalse()
        ->and($inTeam2->hasAllPermissions('users.edit', 'posts.edit'))->toBeFalse();
});

test('wildcards see the permissions of global roles', function () {
    $user = testUser('wild@example.com');
    $user->assignGlobalRole('admin');

    atTeam(3);
    $user = $user->fresh();

    expect($user->hasPermissionTo('users.*'))->toBeTrue()
        ->and($user->hasPermissionTo('billing.*'))->toBeFalse()
        ->and($user->getWildcardPermissions('users.*')->pluck('name')->sort()->values()->all())->toBe(['users.edit', 'users.view'])
        ->and($user->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(['users.edit', 'users.view']);
});

test('the permission middleware check sees them too', function () {
    // `permission:` middleware asks canAny(), which is the Gate.
    $user = testUser('gate@example.com');
    $user->assignGlobalRole('admin');

    atTeam(1);

    expect(Gate::forUser($user->fresh())->any(['billing.view', 'users.view']))->toBeTrue();
});

test('it answers by permission model, and a guard without the permission says so', function () {
    $user = testUser('forms@example.com');
    $user->assignGlobalRole('admin');
    atTeam(1);
    $user = $user->fresh();

    expect($user->hasPermissionTo(Permission::findByName('users.view', 'web')))->toBeTrue()
        ->and(fn () => $user->hasPermissionTo('users.view', 'api'))->toThrow(PermissionDoesNotExist::class);
});

test('a permission that does not exist still says so', function () {
    $user = testUser('missing@example.com');
    $user->assignGlobalRole('admin');
    atTeam(1);

    expect(fn () => $user->fresh()->hasPermissionTo('nope.never'))->toThrow(PermissionDoesNotExist::class)
        ->and($user->fresh()->checkPermissionTo('nope.never'))->toBeFalse();
});

test('a global role is still not a role of the current team', function () {
    // hasRole() stays the current team's question, and so do role: middleware
    // and @role — ask hasGlobalRole(), or better, check a permission.
    $user = testUser('role@example.com');
    $user->assignGlobalRole('admin');
    atTeam(1);

    expect($user->fresh()->hasRole('admin'))->toBeFalse()
        ->and($user->fresh()->hasGlobalRole('admin'))->toBeTrue();
});

test('it reads the global permissions once, however many checks a page makes', function () {
    $user = testUser('cached@example.com');
    $user->assignGlobalRole('admin');
    atTeam(1);
    $user = $user->fresh();
    $user->hasPermissionTo('users.view'); // warm Spatie's caches and ours

    DB::enableQueryLog();

    foreach (range(1, 20) as $_) {
        $user->hasPermissionTo('users.view');
        $user->hasPermissionTo('users.edit');
    }

    expect(DB::getQueryLog())->toBe([]);
});

test('an account with no global roles never queries for their permissions', function () {
    $user = testUser('plain@example.com');
    atTeam(1);
    $user = $user->fresh();

    DB::enableQueryLog();
    $user->getAllPermissions();
    $user->getAllPermissions();

    $permissionQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'role_has_permissions'));

    expect($permissionQueries)->toHaveCount(0);
});
