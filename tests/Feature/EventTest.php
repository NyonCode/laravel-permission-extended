<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use NyonCode\PermissionExtended\Events\PermissionChanged;
use NyonCode\PermissionExtended\Events\PermissionChangedQuiet;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    permissions(['admin.create', 'posts.edit']);
    $this->user = testUser('evt@example.com');
    config()->set('permission-extended.broadcast_changes', true);
});

describe('events', function () {
    test('give dispatches both', function () {
        Event::fake([PermissionChanged::class, PermissionChangedQuiet::class]);
        $this->user->givePermissionTo('admin.create');
        Event::assertDispatched(PermissionChangedQuiet::class);
        Event::assertDispatched(PermissionChanged::class);
    });

    test('revoke dispatches both', function () {
        $this->user->givePermissionTo('admin.create');
        Event::fake([PermissionChanged::class, PermissionChangedQuiet::class]);
        $this->user->revokePermissionTo('admin.create');
        Event::assertDispatched(PermissionChangedQuiet::class);
        Event::assertDispatched(PermissionChanged::class);
    });

    test('assignRole dispatches both', function () {
        Role::create(['name' => 'a', 'guard_name' => 'web']);
        Event::fake([PermissionChanged::class, PermissionChangedQuiet::class]);
        $this->user->assignRole('a');
        Event::assertDispatched(PermissionChangedQuiet::class);
        Event::assertDispatched(PermissionChanged::class);
    });
});

describe('broadcast disabled', function () {
    test('quiet fires, broadcast does not', function () {
        config()->set('permission-extended.broadcast_changes', false);
        Event::fake([PermissionChanged::class, PermissionChangedQuiet::class]);
        $this->user->givePermissionTo('admin.create');
        Event::assertDispatched(PermissionChangedQuiet::class);
        Event::assertNotDispatched(PermissionChanged::class);
    });
});

describe('payload', function () {
    test('contains action', function () {
        Event::fake([PermissionChangedQuiet::class]);
        $this->user->givePermissionTo('admin.create');
        Event::assertDispatched(PermissionChangedQuiet::class, fn ($e) => $e->changes['action'] === 'give' && $e->user->is($this->user));
    });
});
