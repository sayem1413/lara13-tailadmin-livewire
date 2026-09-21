# Entity Lifecycle & Relationship Integrity

A generic, opt-in module governing how related records behave when a model's
status changes (activate/deactivate/delete/restore/re-parent). It is not wired
into any specific feature (Users, Roles, etc.) - any model in any project
cloned from this template can adopt it independently.

It covers four relationship shapes:

| Shape | Example | What happens on the parent's status change |
|---|---|---|
| **Exclusive** (`hasOne`/`hasMany`) | Organization → Department | Children cascade with the parent |
| **Hierarchical** (exclusive, 3+ layers) | Organization → Department → Employee | Same engine as exclusive, recursively |
| **Shared** (`belongsToMany`) | Category ↔ Product | Only the link is severed - the child is untouched |
| **Self-referential** (`parent_id`) | Folder in Folder | The whole subtree cascades, with cycle prevention |

## Adopting it

Every participating model needs three things:

1. `use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;`
2. `implements LifecycleAware` (which itself requires `LifecycleStatusAware`,
   satisfied automatically by `HasActiveStatus`)
3. The four standard columns, plus `deleted_at`:

```php
$table->string('lifecycle_status')->default('active');
$table->timestamp('activated_at')->nullable();
$table->timestamp('deactivated_at')->nullable();
$table->timestamp('lifecycle_orphaned_at')->nullable();
$table->softDeletes();
```

...and the corresponding cast:

```php
protected function casts(): array
{
    return [
        'lifecycle_status' => \App\Enums\LifecycleStatus::class,
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'lifecycle_orphaned_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
```

`HasActiveStatus` alone (without `HasLifecycleIntegrity`/`LifecycleAware`) is
usable standalone by any model that just wants `activate()`/`deactivate()`
methods with custom events and no relationship rules.

Every foreign key this module touches (a self-referential `parent_id`, a
shared relationship's pivot columns) uses `onDelete('restrict')`, never
`onDelete('cascade')` - see "Why `restrict`, not `cascade`" below.

### The `lifecycleRules()` contract

`LifecycleAware::lifecycleRules()` returns one entry per governed
relationship, keyed by an arbitrary name. A model can declare **both**
directions - what it owns ("downward") and what owns it ("upward") - as
separate entries:

```php
public function lifecycleRules(): array
{
    return [
        // downward: this model cascades to a relation it owns
        'employees' => [
            'type' => 'exclusive',                 // or 'hierarchical'
            'relation' => 'employees',              // a hasMany/morphMany method name
            'cascade' => ['deactivate', 'delete'],  // optional, this is the default
            'restore_strategy' => 'pending_activation', // optional, defaults to config
        ],
        // upward: this model is guarded against its own owner
        'organization' => [
            'type' => 'hierarchical',
            'parent_relation' => 'organization',    // a belongsTo method name
        ],
    ];
}
```

| Type | Keys used | Notes |
|---|---|---|
| `exclusive` / `hierarchical` | `relation` + `cascade` (downward), or `parent_relation` (upward) | See "Why hierarchical isn't a separate engine" below |
| `shared` | `relation`, `inverse_relation`, `orphan_strategy` | See the shared example |
| `self_referential` | `relation` (defaults to `'children'`), `parent_relation` (defaults to `'parent'`), `deletion_strategy` | The FK column must be literally named `parent_id` |

## Worked example: exclusive (Organization → Department)

```php
class Organization extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function lifecycleRules(): array
    {
        return [
            'departments' => [
                'type' => 'exclusive',
                'relation' => 'departments',
                'cascade' => ['deactivate', 'delete'],
                'restore_strategy' => 'pending_activation',
            ],
        ];
    }
}

class Department extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lifecycleRules(): array
    {
        return [
            'organization' => [
                'type' => 'exclusive',
                'parent_relation' => 'organization',
            ],
        ];
    }
}
```

```php
app(LifecycleIntegrityService::class)->deactivate($organization);
// -> every department is deactivated too

app(LifecycleIntegrityService::class)->activate($department);
// -> throws ParentNotActiveException if $organization isn't active
```

## Worked example: hierarchical (Organization → Department → Employee)

Hierarchical is **not** a separate cascade engine. It's the same exclusive
engine, applied recursively: `Department` declares both an upward rule
(against `Organization`) and a downward rule (to `Employee`), and the service
naturally recurses - a deactivated `Organization` cascades to `Department`,
whose own `Deactivated` event cascades to `Employee`. The
"no descendant may be more active than any ancestor" invariant and the
"restoring requires every ancestor active" guard both come from the *same*
ancestor walk (`parent_relation` chased as far as it's declared), so a 2-level
exclusive relationship and a 5-level hierarchy are handled by identical code:

```php
class Department extends Model implements LifecycleAware
{
    public function organization(): BelongsTo { /* ... */ }
    public function employees(): HasMany { /* ... */ }

    public function lifecycleRules(): array
    {
        return [
            'organization' => ['type' => 'hierarchical', 'parent_relation' => 'organization'],
            'employees' => ['type' => 'hierarchical', 'relation' => 'employees', 'cascade' => ['deactivate', 'delete']],
        ];
    }
}
```

Activating an `Employee` directly is blocked if *either* its `Department` or
the `Department`'s `Organization` is inactive - not just the immediate parent.

## Worked example: shared (Category ↔ Product)

```php
class Category extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(CategoryProduct::class)   // a custom Pivot, see below
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');   // required - see "Pivot query convention"
    }

    public function lifecycleRules(): array
    {
        return [
            'products' => [
                'type' => 'shared',
                'relation' => 'products',
                'inverse_relation' => 'categories', // Product's belongsToMany back to Category
                'orphan_strategy' => 'prevent_removal', // 'auto_archive' | 'unassigned' | 'prevent_removal'
            ],
        ];
    }
}
```

`Product` needs the inverse `categories(): BelongsToMany` relation (same
`wherePivotNull('deleted_at')` convention) but doesn't need to declare
anything in `lifecycleRules()` itself - `Category`'s rule already carries
`inverse_relation`, which is all the orphan check needs.

```php
$service->link($category, 'products', $product);    // guards: blocks linking to an inactive/deleted Category
$service->unlink($category, 'products', $product);  // may throw OrphanRemovalBlockedException
```

Deactivating or deleting `$category` **never touches `$product`** - it only
severs the pivot link, then applies `$product`'s orphan_strategy if that was
its last remaining category.

### The pivot itself

Every shared relationship's pivot must be a real model, not a bare array
pivot, so a severed link is soft-removable and auditable:

```php
class CategoryProduct extends Pivot
{
    use SoftDeletes;
}
```

```php
$table->id();
$table->foreignId('category_id')->constrained()->onDelete('restrict');
$table->foreignId('product_id')->constrained()->onDelete('restrict');
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamps();
$table->softDeletes();
```

### Pivot query convention

Eloquent's `SoftDeletes` global scope only applies to a model's *own* table -
`belongsToMany()` queries the pivot table directly (not through the pivot
model's query builder), so it does **not** automatically exclude
soft-removed pivot rows. Every `belongsToMany()`/`morphToMany()` relation
this module governs must add `->wherePivotNull('deleted_at')` itself, as
shown above.

### Orphan strategies

| Strategy | What happens to the child when its last link is severed |
|---|---|
| `auto_archive` | `lifecycle_status` is set to `archived` |
| `unassigned` | `lifecycle_orphaned_at` is set to now - cleared automatically the next time it's linked to anything |
| `prevent_removal` (default) | `unlink()` throws `OrphanRemovalBlockedException` instead of severing the link |

`prevent_removal` only blocks a **direct** `unlink()` call. A parent's own
`deactivate()`/`delete()` cascade can't be blocked by a downstream orphan (the
parent's own action must be able to complete), so a cascade-triggered sever
falls back to `unassigned` instead - see "Flagged decisions" below.

## Worked example: self-referential (Folder in Folder)

```php
class Folder extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }

    public function lifecycleRules(): array
    {
        return [
            'children' => [
                'type' => 'self_referential',
                'relation' => 'children',
                'parent_relation' => 'parent',
                'deletion_strategy' => 'delete_subtree', // or 'promote_children'
            ],
        ];
    }
}
```

```php
$service->deactivate($folder);   // recursively deactivates the whole subtree
$service->delete($folder);       // recursively soft-deletes the whole subtree

$service->deleteNode($folder, DeletionStrategy::DeleteSubtree);   // same as delete() above
$service->deleteNode($folder, DeletionStrategy::PromoteChildren); // re-parents direct children to $folder's own parent, then deletes only $folder

$folder->update(['parent_id' => $someDescendant->id]); // throws CircularReferenceException
```

The strategy passed to `deleteNode()` wins over the rule's own
`deletion_strategy`, which wins over `config('lifecycle.defaults.self_referential.deletion_strategy')`.

### The closure table

A shared, polymorphic `lifecycle_closures` table (`closureable_type`,
`ancestor_id`, `descendant_id`, `depth`) backs every self-referential model -
one table for the whole app, not one per model. It exists so "is X a
descendant of Y" (cycle prevention) and "collect X's whole subtree" are O(1)
indexed reads instead of a recursive query, at the cost of write complexity:
every re-parent (`ClosureTableManager::reparent()`) has to remove the old
external-ancestor links and add new ones for the entire moved subtree, not
just the one node being re-parented.

This trade-off (closure table over a recursive CTE) is scoped to
self-referential only - a heterogeneous multi-model hierarchy (Organization →
Department → Employee) doesn't use it at all; see "Why hierarchical isn't a
separate engine" above.

## Config (`config/lifecycle.php`)

| Key | Meaning |
|---|---|
| `bulk_threshold` | Row count at/above which a relationship's cascade is dispatched to a queued job instead of run inline (default 500) |
| `chunk_size` | Rows per `chunkById()` page, both inline and inside the queued job (default 200) |
| `defaults.exclusive.restore_strategy` | `cascade` or `pending_activation` (default) |
| `defaults.shared.orphan_strategy` | `auto_archive`, `unassigned`, or `prevent_removal` (default) |
| `defaults.self_referential.deletion_strategy` | `promote_children` or `delete_subtree` (default) |
| `queue.connection` / `queue.queue` | Where `CascadeLifecycleActionJob` is dispatched |
| `activity_log.enabled` | Whether cascades write the grouped activity log entry described below |

Every default is overridable per-relationship via the corresponding key in
`lifecycleRules()`.

## Concurrency, bulk cascades, and auditing

- **Transactions**: every `LifecycleIntegrityService` entry point
  (`activate`/`deactivate`/`delete`/`forceDelete`/`restore`/`link`/`unlink`/`deleteNode`)
  wraps its work in `DB::transaction()`. A failure anywhere in a cascade rolls
  back the whole thing, including the triggering model's own change.
- **Locking**: each entry point re-reads the model with `lockForUpdate()`
  before acting, and the ancestor-chain guards do the same when reading a
  parent's status - both to prevent a race between two concurrent actions on
  the same row. SQLite (used by this module's own tests) compiles
  `lockForUpdate()` to nothing, so real lock contention can only be verified
  against MySQL/Postgres.
- **Bulk/queued cascades**: `cascadeChildrenOrQueue()` checks a relation's row
  count before cascading it; at or over `bulk_threshold`, it dispatches
  `CascadeLifecycleActionJob` instead of iterating inline. This only covers
  the deactivate/delete cascades for exclusive/hierarchical/self-referential
  relationships - restore's per-child strategy branching is not queued (see
  "Flagged decisions").
- **Idempotency**: every entry point checks the model's current state first
  and returns immediately if the action is a no-op (already active, already
  inactive, already trashed, not trashed) - no transaction is even opened, and
  no duplicate cascade or log entry is produced.
- **Audit log**: one grouped `spatie/activitylog` entry per triggering action,
  e.g. "Deactivated Organization (cascaded to 3 Department, 42 Employee)",
  with a per-type breakdown in `properties.affected` - never one log row per
  affected record.

## Why `restrict`, not `cascade`

Every FK this module creates uses `onDelete('restrict')`. Cascades are meant
to go through `LifecycleIntegrityService` so guards, orphan handling, and the
configured strategy are respected - a database-level `onDelete('cascade')`
would silently bypass all of that for any raw `DELETE` that reaches the
database directly (a stray query, a manual migration, etc.). `restrict` turns
that mistake into a loud FK constraint error instead of silent data loss.

## Guard failures

`ParentNotActiveException`, `CircularReferenceException`, and
`OrphanRemovalBlockedException` (all in `App\Exceptions\Lifecycle`) are
regular exceptions - catch them at the controller/Livewire layer and surface
them as a SweetAlert2 error toast, the same way other user-facing failures in
this app are handled. This module never fails silently or with a generic 500.

Policies are unaffected: this module governs *what happens* once an action is
authorized, not *who* can trigger it. A model's Policy still decides who may
call `activate`/`deactivate`/`delete`/`restore` in the first place.

## Flagged decisions

A few places where the original spec was ambiguous or in tension with itself,
resolved as follows (documented rather than silently decided):

1. **`prevent_removal` under a parent-triggered cascade.** Blocking a parent's
   own deactivate/delete because a downstream child would be orphaned would
   make any widely-referenced shared parent effectively undeletable. A
   cascade-triggered sever falls back to `unassigned` instead of throwing;
   only a direct `unlink()` call can be blocked.
2. **Transactions vs. queued cascades.** A single `DB::transaction()`
   spanning a 10,000+ row cascade for the duration of a queued job is itself
   a production risk (long-held locks, deadlock exposure) - the reason the
   spec wants chunking/queueing in the first place. The queued path therefore
   does **not** share the triggering action's transaction; each child
   mutation inside `CascadeLifecycleActionJob` commits on its own. A cascade
   that's partially processed when a queue worker fails is not automatically
   rolled back - only the synchronous (under-threshold) path gets full
   all-or-nothing atomicity.
3. **Restore is not queued.** Only the deactivate/delete cascades for
   exclusive/hierarchical/self-referential relationships check the bulk
   threshold. Restore's per-child strategy branching (`cascade` vs
   `pending_activation`) was not extended to the queued path - a very large
   restore cascade still runs inline. Flagging this as a known gap rather
   than a considered trade-off.
4. **Restore vs. activate are orthogonal.** `restore()` only undoes a soft
   delete; it does not also call `activate()`. The `cascade` restore_strategy
   restores *and* activates a relationship's children, independent of
   whether the parent itself is simultaneously activated - a caller wanting
   "restore and activate" as one user-facing action calls both.
5. **Test fixtures live in `tests/Fixtures/Lifecycle`**, not in `app/`, since
   this module isn't wired into any real feature. They're real migrated
   tables (loaded only while `app()->runningUnitTests()`, from
   `AppServiceProvider::boot()`), not files under `database/migrations`.
