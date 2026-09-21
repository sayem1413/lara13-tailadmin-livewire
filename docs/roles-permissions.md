# Roles & Permissions

Covers three things layered onto `spatie/laravel-permission`'s `Role` and
`Permission` models in this app: the `is_active` role-assignment guard, the
activity log fields recorded for both models, and the guard that stops a
role-manager from granting permissions they don't themselves hold.

## `is_active` on Role

`Role` has always had `description` and `is_active` columns (see
`database/migrations/2026_09_18_080000_add_route_sync_columns_to_permission_tables.php`),
but until now neither was validated, writable from a form, shown in a view,
or actually enforced anywhere. Both are now wired up end to end:
`StoreRoleRequest`/`UpdateRoleRequest` validate them, `RoleForm` exposes them
as fields, `RoleService::createRole()`/`updateRole()` persist them, and the
Roles index/show views render `description` and an Active/Inactive badge the
same way Users already does for its own `is_active` column.

### What the guard actually does

`UserService::guardAgainstInactiveRoleAssignment()` runs alongside the
existing `guardAgainstUnassignableSuperAdminRole()` guard in `createUser()`
and `updateUser()`, right before `syncRoles()`. If any role name in the
submitted `roles` array is currently inactive, it throws
`ValidationException::withMessages(['roles' => ...])` - the same shape and
the same `roles` key the Super-Admin-role guard already uses - and nothing is
persisted.

It only runs when a `roles` key is actually present in the data being synced.
`updateUser()` treats `null` (no `roles` key at all) as "leave roles
untouched", so an unrelated field edit on a user who happens to already hold
a role that's since gone inactive is never blocked by this guard - only a
sync that would *(re-)write* an inactive role onto a user is.

### Why a plain boolean, not Lifecycle Integrity

This app also ships an opt-in Entity Lifecycle & Relationship Integrity
module (`docs/lifecycle-integrity.md`) with cascading activate/deactivate,
orphan handling, and closure-table hierarchies. `Role.is_active` deliberately
does **not** adopt it:

- Lifecycle Integrity exists to keep a tree or graph of *owned* records
  consistent when a parent's status changes - cascading deactivation to
  `hasMany`/`belongsToMany` children, blocking activation of a child whose
  parent is inactive, and so on. A `Role` and the `User`s who hold it aren't
  that kind of relationship: deactivating a role doesn't mean anything should
  happen to users who already hold it (see above - they keep it), so there's
  no cascade, no orphan strategy, and no ancestor-chain invariant to enforce.
- The entire ask is "block one specific write" (a new role assignment) under
  one specific condition (the role is inactive). That's a single `where`
  clause and a `ValidationException`, not a relationship-integrity rule.
  Pulling in `HasActiveStatus`/`HasLifecycleIntegrity`, the
  `lifecycle_status`/`activated_at`/`deactivated_at` columns, and a
  `lifecycleRules()` contract for this would be strictly more machinery than
  the requirement, with no behavior it actually needs.

If a future requirement calls for cascading role deactivation (e.g.
auto-revoking a role from every holder when it's deactivated), that's a
deliberate product decision to make at that point - not something this guard
should grow into on its own.

### Role deletion is unaffected

`RoleService::deleteRole()` already refuses to delete a role that's still
assigned to at least one user (`"{$role->name}" is assigned to at least one
user and can't be deleted.`, keyed to `role`). That guard is about
*deletion*, not *assignment*, and needless to say a role can be deleted while
inactive or active alike - `is_active` has no bearing on whether it's safe to
delete, only on whether it's safe to *hand out*. The two guards are
independent and neither was changed to reference the other.

## Activity logging for Role and Permission

`App\Models\User` was the only model using
`Spatie\Activitylog\Models\Concerns\LogsActivity`. `Role` and `Permission`
now use it too, with the same configuration shape as `User::getActivitylogOptions()`
(`logOnly()` + `logOnlyDirty()` + `dontLogEmptyChanges()`) - an explicit
allowlist of real columns, not `logFillable()` or logging every attribute:

| Model | Logged fields |
|---|---|
| `Role` | `name`, `guard_name`, `description`, `is_active` |
| `Permission` | `name`, `guard_name`, `module`, `section`, `description` |

Like `User`, only dirty fields are recorded on update (`logOnlyDirty()`), and
a save that leaves every logged field unchanged produces no log entry at all
(`dontLogEmptyChanges()`). Entries show up in the same Activity Log admin
screen (`admin.activity-log.index`) as every other logged model, read back
through `Activity::attribute_changes` (a `['attributes' => ..., 'old' => ...]`
shape) exactly as the existing activity log UI already reads it for `User`.

## Permission-escalation guard

### The gap this closes

`UserService::guardAgainstUnassignableSuperAdminRole()` only ever stopped one
specific escalation: directly assigning the literal "Super Admin" role to a
user. It did nothing to stop a narrower but just-as-real path: anyone holding
`admin.roles.create`/`admin.roles.edit` could build (or edit) an ordinary
role carrying *any* permission that exists in the system - including ones
they don't personally hold - then self-assign that role via Users. The
Super-Admin-role guard never sees this, because no "Super Admin" role name is
ever involved.

### What it blocks

`RoleService::guardAgainstUnassignablePermissions()` runs in both
`createRole()` and `updateRole()`, after permissions have been expanded with
their implied module-level view permissions (`expandPermissions()` /
`Permission::expandWithImplied()`) but before anything is persisted - so the
check runs against the actual final permission set a role would end up with,
not just the raw checkbox selection. For each permission in that set, it
checks `auth()->user()->can($permissionName)`. If the acting user doesn't
hold even one of them, it throws:

```php
ValidationException::withMessages([
    'permissions' => 'You can only assign permissions you currently hold.',
]);
```

This fires the same way whether the request comes through `RoleForm`
(Livewire), the `RoleController` resource routes, or `RoleService` called
directly - it's a service-layer guard, not a UI-only checkbox restriction, so
a hand-crafted request that skips the rendered permission matrix entirely is
blocked exactly the same as the form.

### Super Admin exemption

A Super Admin actor is exempt (`auth()->user()?->hasRole('Super Admin')`
short-circuits the guard entirely), mirroring how
`guardAgainstUnassignableSuperAdminRole()` exempts Super Admin from its own
check. This is safe by construction, not by trust: `AppServiceProvider`
registers `Gate::before(fn ($user, $ability) => $user->hasRole('Super Admin')
?: null)`, so a Super Admin already holds every permission in the system as
far as `can()` is concerned - re-deriving that one-by-one through this guard
would be redundant, not protective.

### Why this is safe to bootstrap

The obvious worry with any "you can only grant what you hold" rule is a
chicken-and-egg problem: how does the *first* role ever get built? It
doesn't need solving here, because role management itself is gated:
`RolePolicy::create()`/`update()` require `admin.roles.create`/
`admin.roles.edit`, and by default (`database/seeders/RolesAndPermissionsSeeder.php`)
only the "Super Admin" role - which is exempt from this guard entirely - is
granted every permission in the system, including the roles ones. Nobody
else can reach `RoleService::createRole()`/`updateRole()` with a broad
permission set in the first place unless a Super Admin has already granted
them `admin.roles.create`/`admin.roles.edit` *and* whatever specific
permissions they'd go on to assign - which is exactly the invariant this
guard exists to preserve going forward.
