<?php

namespace App\Models\Permission;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * Eloquent does not re-fetch a row after INSERT, so without this a
     * freshly created instance would read is_active as null -> false in
     * memory even though the DB default is true (see App\Models\User).
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
