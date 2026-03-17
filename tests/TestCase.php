<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Tests;

use NyonCode\PermissionExtended\PermissionExtendedServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            PermissionExtendedServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $app['config']->set('permission-extended.cache_per_request', false);

        // Auth config so Spatie can resolve the guard for TestUser
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \TestUser::class,
        ]);
    }

    protected function setUpDatabase(): void
    {
        $stub = __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';

        if (file_exists($stub)) {
            (include $stub)->up();
        }

        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->timestamps();
        });
    }
}
