<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class ActivityLogIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $event = '';

    #[Url]
    public string $subjectType = '';

    /**
     * Flatpickr's range-mode value, formatted "Y-m-d to Y-m-d" (or a
     * single "Y-m-d" while the end date hasn't been picked yet).
     */
    #[Url]
    public string $dateRange = '';

    public int $perPage = 15;

    public function mount(): void
    {
        Gate::authorize('admin.activity-log.index');
    }

    public function updatingSearch(): void
    {
        $this->perPage = 15;
    }

    public function updatingEvent(): void
    {
        $this->perPage = 15;
    }

    public function updatingSubjectType(): void
    {
        $this->perPage = 15;
    }

    public function updatingDateRange(): void
    {
        $this->perPage = 15;
    }

    public function loadMore(): void
    {
        $this->perPage += 15;
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function eventOptions(): Collection
    {
        return app(ActivityLogService::class)->eventOptions();
    }

    /**
     * @return Collection<string, string>
     */
    #[Computed]
    public function subjectTypeOptions(): Collection
    {
        return app(ActivityLogService::class)->subjectTypeOptions();
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    protected function activities(): LengthAwarePaginator
    {
        return app(ActivityLogService::class)->paginate(
            search: $this->search,
            perPage: $this->perPage,
            filters: [
                'event' => $this->event,
                'subjectType' => $this->subjectType,
                'dateRange' => $this->dateRange,
            ],
        );
    }

    public function render(): View
    {
        return view('livewire.admin.activity-log.activity-log-index', [
            'activities' => $this->activities(),
        ]);
    }
}
