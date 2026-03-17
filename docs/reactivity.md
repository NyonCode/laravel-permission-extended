# Reactive Updates

**Level 1 — Same-page** (instant): `$this->dispatch('permissions-changed');`

**Level 2 — Polling** (recommended): `<div wire:poll.30s="checkPermissions">`

**Level 3 — Broadcasting** (requires Echo + queue): `PERMISSION_BROADCAST_CHANGES=true`
