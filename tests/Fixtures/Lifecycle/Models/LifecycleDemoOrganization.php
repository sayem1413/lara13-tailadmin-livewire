<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Contracts\LifecycleAware;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - the
 * top-level, "exclusive owner" side of the Organization -> Department
 * example (see docs/lifecycle-integrity.md).
 */
#[Fillable(['name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoOrganization extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    protected $table = 'lifecycle_demo_organizations';

    protected $attributes = [
        'lifecycle_status' => 'active',
    ];

    /**
     * Test-only knob so different tests can exercise both restore
     * strategies without a second fixture pair.
     */
    public static string $departmentRestoreStrategy = 'pending_activation';

    /**
     * Test-only knob so a test can opt the departments relation into the
     * (off-by-default) 'activate' cascade trigger without a second
     * fixture pair.
     *
     * @var array<int, string>
     */
    public static array $departmentCascade = ['deactivate', 'delete'];

    /**
     * Test-only knob so a test can mark the departments relation
     * `retain` without a second fixture pair.
     */
    public static bool $departmentsRetained = false;

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
     * @return HasMany<LifecycleDemoDepartment, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(LifecycleDemoDepartment::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'departments' => [
                'type' => 'exclusive',
                'relation' => 'departments',
                'cascade' => static::$departmentCascade,
                'restore_strategy' => static::$departmentRestoreStrategy,
                'retain' => static::$departmentsRetained,
            ],
        ];
    }
}
