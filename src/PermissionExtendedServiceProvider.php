<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended;

use Closure;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->beforeInstallation($this->spatiePublishStep())
                    ->publishConfig()
                    ->publishMigrations()
                    ->afterInstallation($this->postInstallStep())
                    ->askToStarRepoOnGitHub('NyonCode/laravel-permission-extended');
            });
    }

    /**
     * Bootstrap the package before the application is fully booted.
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

        Gate::before(static function ($user, string $_ability) use ($role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }

            return null;
        });
    }

    /**
     * Register the middleware.
     *
     * @throws BindingResolutionException
     */
    public function registerMiddleware(): void
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
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Repository' => 'https://github.com/NyonCode/laravel-permission-extended',
            'Author' => 'Ondřej Nyklíček',
        ];
    }

    // =================================================================
    // Install helpers (private — not protected)
    // =================================================================

    /**
     * Return a closure that publishes Spatie config + migrations.
     */
    private function spatiePublishStep(): Closure
    {
        return static function (InstallCommand $cmd): void {
            $cmd->info('Installing Laravel Permission Extended…');
            $cmd->newLine();

            // ── Spatie config ──
            if (! File::exists(config_path('permission.php'))) {
                $cmd->callSilently('vendor:publish', [
                    '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                    '--tag' => 'permission-config',
                ]);
                $cmd->line('  Spatie config [config/permission.php] .......... <info>PUBLISHED</info>');
            } else {
                $cmd->line('  Spatie config [config/permission.php] .......... <comment>EXISTS</comment>');
            }

            // ── Spatie migrations ──
            $existing = File::glob(database_path('migrations/*_create_permission_tables.php'));

            if (empty($existing)) {
                $cmd->callSilently('vendor:publish', [
                    '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                    '--tag' => 'permission-migrations',
                ]);
                $cmd->line('  Spatie migrations [create_permission_tables] ... <info>PUBLISHED</info>');
            } else {
                $cmd->line('  Spatie migrations [create_permission_tables] ... <comment>EXISTS</comment>');
            }
        };
    }

    /**
     * Return a closure that runs migrations and patches the User model.
     */
    private function postInstallStep(): Closure
    {
        return function (InstallCommand $cmd): void {
            $cmd->newLine();

            // ── Run migrations ──
            if ($cmd->confirm('Run database migrations now?', true)) {
                $cmd->call('migrate');
            }

            // ── Patch User model ──
            $this->patchUserModel($cmd);
        };
    }

    /**
     * Offer to replace HasRoles with HasWildcardPermissions in the User model.
     */
    private function patchUserModel(InstallCommand $cmd): void
    {
        $modelPath = collect([
            app_path('Models/User.php'),
            app_path('User.php'),
        ])->first(fn (string $path): bool => File::exists($path));

        if (! is_string($modelPath)) {
            $cmd->warn('Could not locate your User model. Add the trait manually:');
            $cmd->line('  use \NyonCode\PermissionExtended\Traits\HasWildcardPermissions;');

            return;
        }

        try {
            $contents = File::get($modelPath);
        } catch (FileNotFoundException) {
            return;
        }

        $fileName = basename($modelPath);

        if (Str::contains($contents, 'HasWildcardPermissions')) {
            $cmd->line('  User model ['.$fileName.'] ... <info>ALREADY CONFIGURED</info>');

            return;
        }

        if (! $cmd->confirm('Replace HasRoles with HasWildcardPermissions in your User model?', true)) {
            return;
        }

        $updated = $this->applyTraitPatch($contents);

        File::put($modelPath, $updated);

        $cmd->line('  User model ['.$fileName.'] ... <info>PATCHED</info>');
    }

    /**
     * Apply the HasWildcardPermissions trait to file contents.
     */
    private function applyTraitPatch(string $contents): string
    {
        $useImport = 'use NyonCode\PermissionExtended\Traits\HasWildcardPermissions;';

        // Case 1: Already has HasRoles → replace it
        if (Str::contains($contents, 'use HasRoles')) {
            $contents = Str::replace('use HasRoles', 'use HasWildcardPermissions', $contents);

            return Str::replace(
                'use Spatie\Permission\Traits\HasRoles;',
                $useImport,
                $contents
            );
        }

        // Case 2: No HasRoles → add our trait
        $contents = (string) preg_replace(
            '/(use [^;]+;)(\s*class )/m',
            '$1'."\n".$useImport.'$2',
            $contents,
            1
        );

        return (string) preg_replace(
            '/(class User extends \w+[^{]*\{)/',
            "$1\n    use HasWildcardPermissions;\n",
            $contents,
            1
        );
    }
}
