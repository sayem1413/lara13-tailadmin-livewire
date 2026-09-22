<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Contracts\LifecycleAware;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - the third
 * layer of the Organization -> Department -> Employee hierarchical
 * example (see docs/lifecycle-integrity.md, Part C). Its upward
 * "department" rule is what lets the ancestor walk continue past
 * Department to Organization.
 */
#[Fillable(['lifecycle_demo_department_id', 'name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoEmployee extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    protected $table = 'lifecycle_demo_employees';

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
     * @return BelongsTo<LifecycleDemoDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(LifecycleDemoDepartment::class, 'lifecycle_demo_department_id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'department' => [
                'type' => 'hierarchical',
                'parent_relation' => 'department',
            ],
        ];
    }
}
