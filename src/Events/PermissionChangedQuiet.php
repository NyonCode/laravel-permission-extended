<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionChangedQuiet
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly mixed $user,
        public readonly array $changes = [],
    ) {}
}
