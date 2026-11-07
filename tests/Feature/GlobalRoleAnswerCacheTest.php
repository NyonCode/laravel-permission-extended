<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;

/**
 * The super-admin gate asks before every ability check, and without teams the
 * question was Spatie's `hasRole()` every time — through `loadMissing('roles')`,
 * dozens of times on a page whose menu and table rows each check an ability.
 */
class CountingRolesUser extends TestUser
{
    public int $roleQuestions = 0;

    public function hasRole($roles, ?string $guard = null): bool
    {
        $this->roleQuestions++;

        return parent::hasRole($roles, $guard);
    }
}

function countingUser(string $email): CountingRolesUser
{
    return CountingRolesUser::create(['name' => 'Test', 'email' => $email]);
}

it('asks once per model whether it holds the super-admin role, however many abilities are checked', function (): void {
    permissions(['posts.edit']);
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

    $user = countingUser('many@example.com');
    $user->assignRole('super-admin');
    $user->roleQuestions = 0;

    foreach (range(1, 25) as $i) {
        expect($user->can("ability.{$i}"))->toBeTrue();
    }

    expect($user->roleQuestions)->toBe(1);
});

it('asks again after a role is given or taken through the trait', function (): void {
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = countingUser('changes@example.com');

    expect($user->can('anything'))->toBeFalse();

    $user->assignRole('super-admin');
    expect($user->can('anything'))->toBeTrue();

    $user->removeRole('super-admin');
    expect($user->can('anything'))->toBeFalse();

    $user->syncRoles(['super-admin']);
    expect($user->can('anything'))->toBeTrue();
});

it('keeps the answer per model, per role name and per guard', function (): void {
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $admin = countingUser('admin@example.com');
    $admin->assignRole('super-admin');
    $other = countingUser('other@example.com');

    expect($admin->hasGlobalRole('super-admin'))->toBeTrue()
        ->and($admin->hasGlobalRole('editor'))->toBeFalse()
        ->and($admin->hasGlobalRole('super-admin', 'api'))->toBeFalse()
        ->and($other->hasGlobalRole('super-admin'))->toBeFalse()
        // a fresh instance of the same row reads afresh, as the next request does
        ->and(CountingRolesUser::find($other->getKey())->hasGlobalRole('super-admin'))->toBeFalse()
        ->and(CountingRolesUser::find($admin->getKey())->hasGlobalRole('super-admin'))->toBeTrue();
});

it('does not cache a question asked with a list or a role model', function (): void {
    $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    $user = countingUser('list@example.com');
    $user->assignRole($role);
    $user->roleQuestions = 0;

    // Spatie answers a list by asking per item, so count one question's calls
    // and expect a second, identical question to make as many again.
    expect($user->hasGlobalRole(['super-admin']))->toBeTrue();
    $perQuestion = $user->roleQuestions;
    expect($user->hasGlobalRole(['super-admin']))->toBeTrue()
        ->and($user->roleQuestions)->toBe(2 * $perQuestion);

    $user->roleQuestions = 0;
    $user->hasGlobalRole($role);
    $user->hasGlobalRole($role);
    expect($user->roleQuestions)->toBe(2);
});
