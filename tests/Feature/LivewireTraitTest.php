<?php

declare(strict_types=1);

use NyonCode\PermissionExtended\Livewire\WithPermissions;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Minimal test object that uses the trait but is not a real Livewire Component.
// We override permUser() to inject an authenticated user directly.
class FakeLivewireComponent
{
    use WithPermissions;

    private mixed $authUser = null;

    public function setUser(mixed $user): void
    {
        $this->authUser = $user;
    }

    protected function permUser(?string $guard = null): mixed
    {
        return $this->authUser;
    }
}

beforeEach(function () {
    permissions(['admin.create', 'admin.delete', 'admin.users.create', 'posts.edit', 'comments.view']);

    $this->user = testUser('lw@example.com');
    $this->user->givePermissionTo(['admin.create', 'admin.delete', 'admin.users.create', 'posts.edit']);

    $this->component = new FakeLivewireComponent;
    $this->component->setUser($this->user);
});

// -----------------------------------------------------------------
// Boolean checks
// -----------------------------------------------------------------

describe('permCan', function () {
    test('returns true for wildcard match', function () {
        expect($this->component->permCan('admin.*'))->toBeTrue();
    });

    test('returns false for no match', function () {
        expect($this->component->permCan('comments.*'))->toBeFalse();
    });

    test('returns true for exact match', function () {
        expect($this->component->permCan('posts.edit'))->toBeTrue();
    });

    test('returns false for nonexistent permission', function () {
        expect($this->component->permCan('totally.made.up'))->toBeFalse();
    });

    test('returns false when no user', function () {
        $c = new FakeLivewireComponent;
        expect($c->permCan('admin.*'))->toBeFalse();
    });
});

describe('permCanAny', function () {
    test('true when one matches', function () {
        expect($this->component->permCanAny(['comments.*', 'admin.*']))->toBeTrue();
    });

    test('false when none match', function () {
        expect($this->component->permCanAny(['comments.*', 'settings.*']))->toBeFalse();
    });
});

describe('permCanAll', function () {
    test('true when all match', function () {
        expect($this->component->permCanAll(['admin.*', 'posts.edit']))->toBeTrue();
    });

    test('false when one fails', function () {
        expect($this->component->permCanAll(['admin.*', 'comments.*']))->toBeFalse();
    });
});

describe('permCanRoleOr', function () {
    test('true by wildcard permission', function () {
        expect($this->component->permCanRoleOr('manager|admin.*'))->toBeTrue();
    });

    test('true by role', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->user->assignRole('editor');

        expect($this->component->permCanRoleOr('editor|settings.*'))->toBeTrue();
    });

    test('false when neither', function () {
        expect($this->component->permCanRoleOr('manager|settings.*'))->toBeFalse();
    });
});

// -----------------------------------------------------------------
// permMatching
// -----------------------------------------------------------------

describe('permMatching', function () {
    test('returns matching names', function () {
        $result = $this->component->permMatching('admin.*');
        expect($result)->toContain('admin.create', 'admin.delete', 'admin.users.create')
            ->toHaveCount(3);
    });

    test('returns empty for no match', function () {
        expect($this->component->permMatching('settings.*'))->toBeEmpty();
    });

    test('returns empty when no user', function () {
        $c = new FakeLivewireComponent;
        expect($c->permMatching('admin.*'))->toBeEmpty();
    });
});

// -----------------------------------------------------------------
// Authorisation (abort 403)
// -----------------------------------------------------------------

describe('permAuthorize', function () {
    test('passes when permission matches', function () {
        // Should not throw
        $this->component->permAuthorize('admin.*');
        expect(true)->toBeTrue();
    });

    test('aborts 403 when permission does not match', function () {
        expect(fn () => $this->component->permAuthorize('settings.*'))
            ->toThrow(HttpException::class);
    });

    test('aborts 403 when no user', function () {
        $c = new FakeLivewireComponent;
        expect(fn () => $c->permAuthorize('admin.*'))
            ->toThrow(HttpException::class);
    });
});

describe('permAuthorizeAny', function () {
    test('passes when one matches', function () {
        $this->component->permAuthorizeAny(['settings.*', 'admin.*']);
        expect(true)->toBeTrue();
    });

    test('aborts when none match', function () {
        expect(fn () => $this->component->permAuthorizeAny(['settings.*', 'comments.*']))
            ->toThrow(HttpException::class);
    });
});

describe('permAuthorizeAll', function () {
    test('passes when all match', function () {
        $this->component->permAuthorizeAll(['admin.*', 'posts.edit']);
        expect(true)->toBeTrue();
    });

    test('aborts when one fails', function () {
        expect(fn () => $this->component->permAuthorizeAll(['admin.*', 'settings.*']))
            ->toThrow(HttpException::class);
    });
});

describe('permAuthorizeRoleOr', function () {
    test('passes by wildcard', function () {
        $this->component->permAuthorizeRoleOr('nobody|admin.*');
        expect(true)->toBeTrue();
    });

    test('aborts when neither', function () {
        expect(fn () => $this->component->permAuthorizeRoleOr('nobody|settings.*'))
            ->toThrow(HttpException::class);
    });
});

// -----------------------------------------------------------------
// Snapshot / permissionsChanged
// -----------------------------------------------------------------

describe('permissionsChanged', function () {
    test('returns false when nothing changed', function () {
        $this->component->mountWithPermissions();
        expect($this->component->permissionsChanged())->toBeFalse();
    });

    test('returns true after permission added', function () {
        $this->component->mountWithPermissions();

        $this->user->givePermissionTo('comments.view');

        expect($this->component->permissionsChanged())->toBeTrue();
    });

    test('returns true after permission revoked', function () {
        $this->component->mountWithPermissions();

        $this->user->revokePermissionTo('admin.create');

        expect($this->component->permissionsChanged())->toBeTrue();
    });

    test('returns false on second call without changes', function () {
        $this->component->mountWithPermissions();

        $this->user->givePermissionTo('comments.view');
        $this->component->permissionsChanged(); // first call updates snapshot

        expect($this->component->permissionsChanged())->toBeFalse();
    });
});

// -----------------------------------------------------------------
// mountWithPermissions
// -----------------------------------------------------------------

describe('mountWithPermissions', function () {
    test('sets permUserId', function () {
        $this->component->mountWithPermissions();
        expect($this->component->permUserId)->toBe($this->user->id);
    });

    test('permUserId is null when no user', function () {
        $c = new FakeLivewireComponent;
        $c->mountWithPermissions();
        expect($c->permUserId)->toBeNull();
    });
});

// -----------------------------------------------------------------
// getListeners
// -----------------------------------------------------------------

describe('getListeners', function () {
    test('always includes permissions-changed', function () {
        $listeners = $this->component->getListeners();
        expect($listeners)->toHaveKey('permissions-changed');
    });

    test('excludes echo listener when broadcast disabled', function () {
        config()->set('permission-extended.broadcast_changes', false);
        $this->component->mountWithPermissions();

        $listeners = $this->component->getListeners();
        expect($listeners)->not->toHaveKey("echo-private:permissions.{$this->user->id},PermissionChanged");
    });

    test('includes echo listener when broadcast enabled and user set', function () {
        config()->set('permission-extended.broadcast_changes', true);
        $this->component->mountWithPermissions();

        $listeners = $this->component->getListeners();
        expect($listeners)->toHaveKey("echo-private:permissions.{$this->user->id},PermissionChanged");
    });
});
