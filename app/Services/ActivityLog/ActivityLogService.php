<?php

namespace App\Services\ActivityLog;

use App\Repositories\Interfaces\ActivityLog\ActivityLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function __construct(
        protected ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(?string $search = null, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        // $search is bound straight into a LIKE '%...%' (plus an
        // orWhereHasMorph against the causer's name) with no length limit
        // of its own - see ActivityLogRepository::paginate(). Clamping it
        // here keeps that query bounded regardless of what a tampered
        // request sends, without changing behavior for any real search.
        if ($search !== null) {
            $search = mb_substr($search, 0, 255);
        }

        return $this->activityLogRepository->paginate($search, $perPage, $filters);
    }

    /**
     * @return Collection<int, string>
     */
    public function eventOptions(): Collection
    {
        return $this->activityLogRepository->eventOptions();
    }

    /**
     * @return Collection<string, string>
     */
    public function subjectTypeOptions(): Collection
    {
        return $this->activityLogRepository->subjectTypeOptions();
    }
}
