<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    permissions(['admin.create', 'admin.delete', 'posts.edit', 'comments.view']);

    $this->user = testUser('blade@example.com');
    $this->user->givePermissionTo(['admin.create', 'admin.delete', 'posts.edit']);

    $this->actingAs($this->user);
});

function renderBlade(string $template, array $data = []): string
{
    return trim(Blade::render($template, $data));
}

// -----------------------------------------------------------------
// @canPermission
// -----------------------------------------------------------------

describe('@canPermission', function () {
    test('renders content when wildcard matches', function () {
        $html = renderBlade('
            @canPermission("admin.*")
                YES
            @endcanPermission
        ');

        expect($html)->toBe('YES');
    });

    test('hides content when wildcard does not match', function () {
        $html = renderBlade('
            @canPermission("comments.*")
                YES
            @endcanPermission
        ');

        expect($html)->toBe('');
    });

    test('works with exact permission', function () {
        $html = renderBlade('
            @canPermission("posts.edit")
                YES
            @endcanPermission
        ');

        expect($html)->toBe('YES');
    });

    test('supports else branch', function () {
        $html = renderBlade('
            @canPermission("settings.*")
                YES
            @else 
                NO
            @endcanPermission
        ');
        expect($html)->toBe('NO');
    });
});

// -----------------------------------------------------------------
// @canAnyPermission
// -----------------------------------------------------------------

describe('@canAnyPermission', function () {
    test('renders when one pattern matches', function () {
        $html = renderBlade('
            @canAnyPermission(["comments.*", "admin.*"])
                YES
            @endcanAnyPermission
        ');
        expect($html)->toBe('YES');
    });

    test('hides when none match', function () {
        $html = renderBlade('
            @canAnyPermission(["comments.*", "settings.*"])
                YES
            @endcanAnyPermission
        ');
        expect($html)->toBe('');
    });
});

// -----------------------------------------------------------------
// @canAllPermissions
// -----------------------------------------------------------------

describe('@canAllPermissions', function () {
    test('renders when all patterns match', function () {
        $html = renderBlade('
            @canAllPermissions(["admin.*", "posts.edit"])
                YES
            @endcanAllPermissions
        ');
        expect($html)->toBe('YES');
    });

    test('hides when one pattern fails', function () {
        $html = renderBlade('
            @canAllPermissions(["admin.*", "comments.*"])
                YES
            @endcanAllPermissions
        ');
        expect($html)->toBe('');
    });
});

// -----------------------------------------------------------------
// @hasRoleOrPermission
// -----------------------------------------------------------------

describe('@hasRoleOrPermission', function () {
    test('renders when wildcard permission matches', function () {
        $html = renderBlade('
            @hasRoleOrPermission("manager|admin.*")
                YES
            @endhasRoleOrPermission
        ');
        expect($html)->toBe('YES');
    });

    test('renders when role matches', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->user->assignRole('editor');

        $html = renderBlade('
            @hasRoleOrPermission("editor|settings.*")
                YES
            @endhasRoleOrPermission
        ');
        expect($html)->toBe('YES');
    });

    test('hides when neither matches', function () {
        $html = renderBlade('
            @hasRoleOrPermission("manager|settings.*")
                YES
            @endhasRoleOrPermission
        ');
        expect($html)->toBe('');
    });
});

// -----------------------------------------------------------------
// @unlessCanPermission
// -----------------------------------------------------------------

describe('@unlessCanPermission', function () {
    test('renders when user lacks permission', function () {
        $html = renderBlade('
            @unlessCanPermission("settings.*")
                NOPE
            @endunlessCanPermission
        ');
        expect($html)->toBe('NOPE');
    });

    test('hides when user has permission', function () {
        $html = renderBlade('
            @unlessCanPermission("admin.*")
                NOPE
            @endunlessCanPermission
        ');
        expect($html)->toBe('');
    });
});

// -----------------------------------------------------------------
// Guest (no auth)
// -----------------------------------------------------------------

describe('guest', function () {
    test('@canPermission hides for guest', function () {
        auth()->logout();
        $html = renderBlade('
            @canPermission("admin.*")
                YES
            @endcanPermission
        ');
        expect($html)->toBe('');
    });

    test('@unlessCanPermission renders for guest', function () {
        auth()->logout();
        $html = renderBlade('
            @unlessCanPermission("admin.*")
                GUEST
            @endunlessCanPermission
        ');
        expect($html)->toBe('GUEST');
    });
});

// -----------------------------------------------------------------
// Native @can works with wildcards
// -----------------------------------------------------------------

describe('@can', function () {
    test('native @can works with wildcard', function () {
        $html = renderBlade('
            @can("admin.*")
                YES
            @endcan
        ');
        expect($html)->toBe('YES');
    });

    test('native @can hides on no match', function () {
        $html = renderBlade('
            @can("settings.*")
                YES
            @endcan
        ');
        expect($html)->toBe('');
    });
});
