<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\PermissionExtended\Tests\TestCase;
use NyonCode\PermissionExtended\Traits\HasWildcardPermissions;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Test Model
|--------------------------------------------------------------------------
*/

class TestUser extends Authenticatable
{
    use HasWildcardPermissions;

    protected $table = 'users';

    protected $guarded = [];

    protected $guard_name = 'web';
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function testUser(string $email = 'test@example.com'): TestUser
{
    return TestUser::create(['name' => 'Test', 'email' => $email]);
}

function permissions(array $names): void
{
    foreach ($names as $name) {
        Permission::findOrCreate($name, 'web');
    }
}
