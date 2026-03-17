<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Contracts\Packable;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class PermissionExtendedServiceProvider extends PackageServiceProvider implements Packable
{
    public function configure(Packager $packager): void
    {
        $packager
            ->name('Laravel Permission Extended')
            ->hasShortName('permission-extended')
            ->hasConfig()
            ->hasAbout()
            ->hasCommands([
                Commands\FlushPermissionCacheCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('NyonCode/laravel-permission-extended');
            });
    }

    public function bootingPackage(): void
    {
        $this->registerSuperAdmin();
        $this->registerMiddleware();
    }

    public function bootedPackage(): void
    {
        Blade\Directives::register();
        $this->registerBroadcastChannel();
        $this->app->terminating(fn () => WildcardChecker::flush());
    }

    // =================================================================
    // Super-Admin Gate
    // =================================================================

    protected function registerSuperAdmin(): void
    {
        $role = config('permission-extended.super_admin_role');

        if ($role === null || $role === '' || $role === false) {
            return;
        }

        Gate::before(function ($user, string $ability) use ($role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }

            return null;
        });
    }

    // =================================================================
    // Spatie Middleware Auto-Registration
    // =================================================================

    protected function registerMiddleware(): void
    {
        if (! config('permission-extended.register_middleware', true)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    // =================================================================
    // Broadcast Channel
    // =================================================================

    protected function registerBroadcastChannel(): void
    {
        try {
            Broadcast::channel(
                'permissions.{userId}',
                fn ($user, $userId) => (int) $user->getKey() === (int) $userId
            );
        } catch (\Throwable) {
            // Broadcasting not configured.
        }
    }

    public function aboutData(): array
    {
        return [
            'Autor' => 'Ondřej Nyklíček',
        ];

    }
}
