<?php

namespace App\Models\Permission;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'guard_name', 'description', 'is_active'])]
class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * Eloquent does not re-fetch a row after INSERT, so without this a
     * freshly created instance would read is_active as null -> false in
     * memory even though the DB default is true (see App\Models\User).
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
