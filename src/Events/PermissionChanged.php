<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $userId;

    public function __construct(
        public readonly mixed $user,
        public readonly array $changes = [],
    ) {
        $this->userId = $user->getKey();
    }

    /** @return array<int,Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("permissions.{$this->userId}")];
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'changes' => $this->changes,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('permission-extended.broadcast_changes', false);
    }
}
