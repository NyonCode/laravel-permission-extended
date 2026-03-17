<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Commands;

use Illuminate\Console\Command;
use NyonCode\PermissionExtended\WildcardChecker;
use Spatie\Permission\PermissionRegistrar;

/**
 * Flush Spatie permission cache and wildcard permission cache.
 */
class FlushPermissionCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush the Spatie permission cache and the wildcard pattern cache.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        WildcardChecker::flush();

        $this->components->info('Permission caches flushed successfully.');

        return self::SUCCESS;
    }
}
