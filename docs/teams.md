# Teams and Global Roles

With Spatie's teams on (`config/permission.php` → `'teams' => true`), every role
assignment belongs to a team, and `hasRole()` only sees the assignments of the
team that is current (`setPermissionsTeamId()`). That is right for an editor of
one team. It is wrong for a role that has to count everywhere — a super-admin
assigned in team 1 would bypass every check while team 1 is current and nothing
anywhere else.

## Global roles

A global role is assigned once and counts in every team:

```php
$user->assignGlobalRole('super-admin');
$user->hasGlobalRole('super-admin');        // true, whatever team is current
$user->removeGlobalRole('super-admin');
```

- The role itself must be a **global role** — one with no team (`team_id` null).
  A role a team owns cannot be assigned globally; the lookup does not find it and
  Spatie's `RoleDoesNotExist` is thrown.
- A global assignment is **not** a role of the current team: `hasRole()` inside a
  team does not see it. Ask `hasGlobalRole()`.
- `hasGlobalRole()` reads the assignments once per model instance, so the
  super-admin gate costs one query however many checks a page makes. Assigning or
  removing a role clears it.
- Without teams, the global methods are the plain role methods.

## How it is stored

Spatie's pivot makes the team column part of its primary key, so it cannot be
null. A global assignment is stored under a reserved team id:

```php
// config/permission-extended.php
'global_team_id' => env('PERMISSION_GLOBAL_TEAM_ID', 0),
```

It must never be the id of a real team. `0` is safe for auto-increment ids; an
application whose teams use UUIDs sets a value its teams can never have.

## The super-admin gate

With teams on, the gate honours **only** a global assignment of
`super_admin_role`. A super-admin assigned inside a team is an ordinary role of
that team and no longer bypasses anything — assign it with `assignGlobalRole()`.

A model on Spatie's own `HasRoles` trait has no global roles, so with teams on it
gets no bypass. Without teams nothing changes.
