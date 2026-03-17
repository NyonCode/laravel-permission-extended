<?php

declare(strict_types=1);

use NyonCode\PermissionExtended\WildcardChecker;

beforeEach(fn () => WildcardChecker::flush());

describe('matches', function () {
    test('exact', fn () => expect(WildcardChecker::matches('a.b', 'a.b'))->toBeTrue());
    test('exact miss', fn () => expect(WildcardChecker::matches('a.b', 'a.c'))->toBeFalse());
    test('trailing *', fn () => expect(WildcardChecker::matches('admin.*', 'admin.create'))->toBeTrue());
    test('deep *', fn () => expect(WildcardChecker::matches('admin.*', 'admin.users.create'))->toBeTrue());
    test('leading *', fn () => expect(WildcardChecker::matches('*.delete', 'posts.delete'))->toBeTrue());
    test('middle *', fn () => expect(WildcardChecker::matches('a.*.c', 'a.b.c'))->toBeTrue());
    test('bare *', fn () => expect(WildcardChecker::matches('*', 'anything.here'))->toBeTrue());
    test('no implicit *', fn () => expect(WildcardChecker::matches('admin.*', 'admin'))->toBeFalse());
    test('dots escaped', fn () => expect(WildcardChecker::matches('a.b', 'aXb'))->toBeFalse());
    test('empty = empty', fn () => expect(WildcardChecker::matches('', ''))->toBeTrue());
    test('empty pattern', fn () => expect(WildcardChecker::matches('', 'a'))->toBeFalse());
    test('empty name', fn () => expect(WildcardChecker::matches('a.*', ''))->toBeFalse());
});

describe('filter', function () {
    test('wildcard', function () {
        $result = WildcardChecker::filter('admin.*', collect(['admin.create', 'admin.delete', 'posts.edit']));
        expect($result->all())->toBe(['admin.create', 'admin.delete']);
    });

    test('exact', function () {
        $result = WildcardChecker::filter('posts.edit', ['posts.edit', 'posts.delete']);
        expect($result->all())->toBe(['posts.edit']);
    });

    test('no match', function () {
        expect(WildcardChecker::filter('x.*', ['a.b']))->toBeEmpty();
    });

    test('empty input', function () {
        expect(WildcardChecker::filter('a.*', []))->toBeEmpty();
    });
});

describe('cache', function () {
    test('flush resets', function () {
        WildcardChecker::matches('a.*', 'a.b');
        WildcardChecker::flush();
        expect(WildcardChecker::matches('a.*', 'a.b'))->toBeTrue();
    });
});
