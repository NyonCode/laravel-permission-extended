<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use NyonCode\PermissionExtended\PermissionExtendedServiceProvider;

describe('service provider', function () {
    test('merges config with all keys', function () {
        $config = config('permission-extended');

        expect($config)->toBeArray()
            ->and($config)->toHaveKeys([
                'super_admin_role',
                'register_middleware',
                'cache_per_request',
                'blade_prefix',
                'livewire_auto_refresh',
                'broadcast_changes',
            ]);
    });

    test('config defaults are correct', function () {
        expect(config('permission-extended.super_admin_role'))->toBe('super-admin')
            ->and(config('permission-extended.register_middleware'))->toBeTrue()
            ->and(config('permission-extended.cache_per_request'))->toBeFalse() // overridden in TestCase
            ->and(config('permission-extended.blade_prefix'))->toBe('')
            ->and(config('permission-extended.livewire_auto_refresh'))->toBeTrue()
            ->and(config('permission-extended.broadcast_changes'))->toBeFalse();
    });

    test('config is publishable', function () {
        $publishes = collect(ServiceProvider::$publishes)
            ->flatten()
            ->filter(fn ($path) => str_contains($path, 'permission-extended'));

        expect($publishes)->not->toBeEmpty();
    });

    test('blade directives compile', function () {
        $compiled = Blade::compileString('@canPermission("test")x@endcanPermission');
        expect($compiled)->toContain('Blade::check');
    });

    test('package provider is registered', function () {
        $provider = app()->getProvider(PermissionExtendedServiceProvider::class);
        expect($provider)->not->toBeNull();
    });

    test('install command is registered', function () {
        $commands = Artisan::all();
        expect($commands)->toHaveKey('permission-extended:install');
    });

    test('flush command is registered', function () {
        $commands = Artisan::all();
        expect($commands)->toHaveKey('permission:flush');
    });
});
