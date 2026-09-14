<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/*
 * Roles that count in every team, and the super-admin that needs one.
 *
 * With teams on, Spatie scopes every assignment to the current team, so a
 * super-admin assigned in team 1 bypassed every check while team 1 was current
 * and nothing anywhere else — "can do everything" only some of the time.
 */

function inTeam(int|string|null $team): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($team);
}

beforeEach(function () {
    // A global role belongs to no team.
    inTeam(null);
    Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web']);
    permissions(['posts.edit']);
});

test('a global super-admin bypasses the gate in every team, and outside any', function () {
    $user = testUser('root@example.com');
    $user->assignGlobalRole('super-admin');

    foreach ([1, 2, null] as $team) {
        inTeam($team);
        $user = $user->fresh();

        expect($user->can('posts.edit'))->toBeTrue()
            ->and($user->can('anything.at.all'))->toBeTrue();
    }
});

test('a super-admin assigned in one team is not a super-admin', function () {
    $user = testUser('team-root@example.com');

    inTeam(1);
    $user->assignRole('super-admin');

    // It is still a role in team 1 — and not the bypass, there or anywhere.
    expect($user->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and($user->fresh()->hasGlobalRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->can('posts.edit'))->toBeFalse();
});

test('the assignment is stored under the reserved team id', function () {
    $user = testUser('stored@example.com');
    $user->assignGlobalRole('super-admin');

    expect(DB::table('model_has_roles')->value('team_id'))->toEqual(0);
});

test('the reserved id is configurable, for applications whose teams are not auto-increment', function () {
    config()->set('permission-extended.global_team_id', 'global');

    // The column is an integer in Spatie's migration; an application on UUID
    // teams has changed it, and so does this test.
    DB::statement('DROP TABLE model_has_roles');
    DB::statement('CREATE TABLE model_has_roles (role_id INTEGER, model_type VARCHAR, model_id INTEGER, team_id VARCHAR, PRIMARY KEY (team_id, role_id, model_id, model_type))');

    $user = testUser('uuid@example.com');
    $user->assignGlobalRole('super-admin');

    expect(DB::table('model_has_roles')->value('team_id'))->toBe('global')
        ->and($user->fresh()->hasGlobalRole('super-admin'))->toBeTrue();
});

test('it leaves the current team as it found it', function () {
    inTeam(7);

    testUser('restore@example.com')->assignGlobalRole('super-admin');

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe(7);
});

test('a role a team owns cannot be made global', function () {
    Role::create(['name' => 'team-admin', 'guard_name' => 'web', 'team_id' => 3]);

    expect(fn () => testUser('owned@example.com')->assignGlobalRole('team-admin'))
        ->toThrow(RoleDoesNotExist::class);
});

test('removing a global role takes the bypass away', function () {
    $user = testUser('removed@example.com');
    $user->assignGlobalRole('super-admin');
    $user->removeGlobalRole('super-admin');

    inTeam(1);

    expect($user->fresh()->hasGlobalRole('super-admin'))->toBeFalse()
        ->and($user->fresh()->can('posts.edit'))->toBeFalse();
});

test('a global assignment does not show up as a role of the current team', function () {
    $user = testUser('separate@example.com');
    $user->assignGlobalRole('super-admin');

    inTeam(1);

    expect($user->fresh()->hasRole('super-admin'))->toBeFalse();
});

test('it answers by guard, by role model and by any of a list', function () {
    $user = testUser('forms@example.com');
    $user->assignGlobalRole('super-admin');
    $role = Role::findByName('super-admin', 'web');

    expect($user->hasGlobalRole('super-admin', 'web'))->toBeTrue()
        ->and($user->hasGlobalRole('super-admin', 'api'))->toBeFalse()
        ->and($user->hasGlobalRole($role))->toBeTrue()
        ->and($user->hasGlobalRole(['editor', 'super-admin']))->toBeTrue()
        ->and($user->hasGlobalRole(['editor']))->toBeFalse();
});

test('it reads the assignments once per model, however many checks a page makes', function () {
    $user = testUser('cached@example.com');
    $user->assignGlobalRole('super-admin');
    $user = $user->fresh();

    DB::enableQueryLog();

    foreach (range(1, 20) as $_) {
        $user->hasGlobalRole('super-admin');
    }

    expect(count(DB::getQueryLog()))->toBe(1);
});

test('a model on Spatie\'s own trait gets no bypass with teams on', function () {
    $user = SpatieOnlyTeamsUser::create(['name' => 'Spatie', 'email' => 'spatie@example.com']);

    inTeam(1);
    $user->assignRole('super-admin');

    expect($user->fresh()->can('posts.edit'))->toBeFalse();
});

class SpatieOnlyTeamsUser extends User
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $guard_name = 'web';
}
