<?php

namespace App\Repositories\Interfaces\ActivityLog;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

interface ActivityLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(?string $search = null, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * @return Collection<int, string>
     */
    public function eventOptions(): Collection;

    /**
     * @return Collection<string, string>
     */
    public function subjectTypeOptions(): Collection;
}
