<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Non-broadcast event emitted for permission changes.
 */
class PermissionChangedQuiet
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param mixed $user
     * @param array<string,mixed> $changes
     */
    public function __construct(
        public readonly mixed $user,
        public readonly array $changes = [],
    ) {}
}
