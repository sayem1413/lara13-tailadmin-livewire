<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Contracts\LifecycleAware;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - the middle
 * layer of the Organization -> Department -> Employee hierarchical
 * example (see docs/lifecycle-integrity.md, Part C). Declares both an
 * upward guard (against its organization) and a downward cascade (to its
 * employees) - the ancestor walk and cascade recursion both continue
 * through this same rule shape at each level, with no special
 * "hierarchical" engine of its own.
 */
#[Fillable(['lifecycle_demo_organization_id', 'name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoDepartment extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    protected $table = 'lifecycle_demo_departments';

    protected $attributes = [
        'lifecycle_status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_status' => LifecycleStatus::class,
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'lifecycle_orphaned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LifecycleDemoOrganization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(LifecycleDemoOrganization::class, 'lifecycle_demo_organization_id');
    }

    /**
     * @return HasMany<LifecycleDemoEmployee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(LifecycleDemoEmployee::class);
    }

    /**
     * Test-only knob, mirroring LifecycleDemoOrganization's, so
     * different tests can exercise both restore strategies at this
     * level of the chain too.
     */
    public static string $employeeRestoreStrategy = 'pending_activation';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'organization' => [
                'type' => 'hierarchical',
                'parent_relation' => 'organization',
            ],
            'employees' => [
                'type' => 'hierarchical',
                'relation' => 'employees',
                'cascade' => ['deactivate', 'delete'],
                'restore_strategy' => static::$employeeRestoreStrategy,
            ],
        ];
    }
}
