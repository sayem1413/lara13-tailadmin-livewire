<?php

namespace App\Repositories\Eloquent\ActivityLog;

use App\Models\User;
use App\Repositories\Interfaces\ActivityLog\ActivityLogRepositoryInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function __construct(
        protected Activity $model
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(?string $search = null, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        // Only 'causer' is eager-loaded: the view reads subject_type (a
        // plain column) via class_basename(), never the 'subject' morphTo
        // relation itself, so loading it would just be a wasted query.
        $query = $this->model->query()->with('causer');

        // Not applySearch(): a search term must also match the causer's
        // name via a morph relation, which is a single column list can't
        // express, and needs to sit inside the SAME grouped OR as the
        // description match so it stays properly parenthesized alongside
        // the event/subjectType/dateRange filters below (an ungrouped
        // top-level orWhereHasMorph would OR against the entire query
        // instead of just the description match).
        if (! empty($search)) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhereHasMorph('causer', [User::class], function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['subjectType'])) {
            $query->where('subject_type', $filters['subjectType']);
        }

        if (! empty($filters['dateRange'])) {
            [$from, $to] = array_pad(array_map('trim', explode(' to ', $filters['dateRange'], 2)), 2, null);

            $query->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
                ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to));
        }

        // Not applySort()'s 'newest' shortcut, which orders by id - this
        // orders by created_at instead, exactly reproducing the previous
        // ->latest() call (the two agree in practice, but aren't the same
        // thing, e.g. after a seeder inserts rows with backdated timestamps).
        $query->latest();

        return $query->paginate($perPage, page: 1);
    }

    /**
     * @return Collection<int, string>
     */
    public function eventOptions(): Collection
    {
        return $this->model->query()
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');
    }

    /**
     * @return Collection<string, string>
     */
    public function subjectTypeOptions(): Collection
    {
        return $this->model->query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type) => [$type => class_basename($type)])
            ->sort();
    }
}
