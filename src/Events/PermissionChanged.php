<?php

declare(strict_types=1);

namespace NyonCode\PermissionExtended\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event when permissions change.
 */
class PermissionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Resolved user ID for the broadcast channel.
     */
    public readonly int $userId;

    /**
     * Create a new event instance.
     *
     * @param  array<string,mixed>  $changes
     */
    public function __construct(
        public readonly mixed $user,
        public readonly array $changes = [],
    ) {
        $this->userId = $user->getKey();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int,Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("permissions.{$this->userId}")];
    }

    /**
     * Get the broadcast payload.
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'changes' => $this->changes,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine whether the event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        return (bool) config('permission-extended.broadcast_changes', false);
    }
}
