<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Contracts\Packable;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Throwable;

/**
 * Service provider for Laravel Permission Extended.
 */
class PermissionExtendedServiceProvider extends PackageServiceProvider implements Packable
{
    /**
     * Register any package services.
     *
     *
     * @throws Exception
     */
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

    /**
     * Bootstrap the package before the application is fully booted.
     *
     *
     * @throws BindingResolutionException
     */
    public function bootingPackage(): void
    {
        $this->registerSuperAdmin();
        $this->registerMiddleware();
    }

    /**
     * Bootstrap the package after the application has booted.
     */
    public function bootedPackage(): void
    {
        Blade\Directives::register();
        $this->registerBroadcastChannel();
        $this->app->terminating(fn () => WildcardChecker::flush());
    }

    /**
     * Register the super admin gate.
     */
    public function registerSuperAdmin(): void
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

    /**
     * Register the middleware.
     *
     *
     * @throws BindingResolutionException
     */
    public function registerMiddleware(): void
    {
        if (! config('permission-extended.register_middleware', true)) {
            return;
        }

        /** @var Route $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    /**
     * Register the broadcast channel.
     */
    public function registerBroadcastChannel(): void
    {
        try {
            Broadcast::channel(
                'permissions.{userId}',
                fn ($user, $userId) => (int) $user->getKey() === (int) $userId
            );
        } catch (Throwable) {
            // Broadcasting not configured.
        }
    }

    /**
     * Get the package's about data.
     *
     * @return array<string,string>
     */
    public function aboutData(): array
    {
        return [
            'Repository' => 'https://github.com/NyonCode/laravel-permission-extended',
            'Author' => 'Ondřej Nyklíček',
        ];

    }
}
