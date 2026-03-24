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
use NyonCode\PermissionExtended\Blade\Directives;
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
                    ->askToStarRepoOnGitHub('https://github.com/NyonCode/laravel-permission-extended');
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
        Directives::register();
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
     * Offer to add or replace HasRoles with NyonCode HasRoles in the User model.
     *
     * @throws FileNotFoundException
     */
    private function patchUserModel(InstallCommand $cmd): void
    {
        $modelPath = collect([
            app_path('Models/User.php'),
            app_path('User.php'),
        ])->first(fn (string $path): bool => File::exists($path));

        if (! is_string($modelPath)) {
            $cmd->warn('Could not locate your User model. Add the trait manually:');
            $cmd->line('  use NyonCode\PermissionExtended\Traits\HasRoles;');

            return;
        }

        $contents = File::get($modelPath);
        $fileName = basename($modelPath);

        if (Str::contains($contents, 'NyonCode\PermissionExtended\Traits\HasRoles')) {
            $cmd->line('  User model ['.$fileName.'] ... <info>ALREADY CONFIGURED</info>');

            return;
        }

        $hasSpatie = Str::contains($contents, 'Spatie\Permission\Traits\HasRoles');

        if ($hasSpatie) {
            if (! $cmd->confirm('Spatie\\Permission\\Traits\\HasRoles detected in ['.$fileName.']. Replace with NyonCode HasRoles?', true)) {
                $cmd->line('  User model ['.$fileName.'] ... <comment>SKIPPED</comment>');

                return;
            }
        } elseif (! $cmd->confirm('Add NyonCode\\PermissionExtended\\Traits\\HasRoles to ['.$fileName.'] automatically?', true)) {
            $cmd->line('  User model ['.$fileName.'] ... <comment>SKIPPED</comment>');

            return;
        }

        File::put($modelPath, $this->applyTraitPatch($contents, $hasSpatie));

        $cmd->line('  User model ['.$fileName.'] ... <info>PATCHED</info>');
    }

    /**
     * Apply the HasRoles trait to file contents.
     */
    private function applyTraitPatch(string $contents, bool $replaceSpatie = false): string
    {
        $import = 'use NyonCode\PermissionExtended\Traits\HasRoles;';

        // Case 1: Replace Spatie import — use statement inside class stays as-is
        if ($replaceSpatie) {
            return Str::replace('use Spatie\Permission\Traits\HasRoles;', $import, $contents);
        }

        // Case 2: No HasRoles — insert import after the last top-level use statement
        if (preg_match_all('/^use [^;]+;$/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $lastUse = end($matches[0]);
            $pos = $lastUse[1] + strlen($lastUse[0]);
            $contents = substr($contents, 0, $pos)."\n".$import.substr($contents, $pos);
        }

        // Add `use HasRoles;` at the top of the class body
        return (string) preg_replace(
            '/(class\s+\w+[^{]*\{)/s',
            "$1\n    use HasRoles;\n",
            $contents,
            1
        );
    }
}
