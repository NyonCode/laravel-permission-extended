<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Commands;

use Illuminate\Console\Command;
use NyonCode\PermissionExtended\WildcardChecker;
use Spatie\Permission\PermissionRegistrar;

class FlushPermissionCacheCommand extends Command
{
    protected $signature = 'permission:flush';

    protected $description = 'Flush the Spatie permission cache and the wildcard pattern cache.';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        WildcardChecker::flush();

        $this->components->info('Permission caches flushed successfully.');

        return self::SUCCESS;
    }
}
