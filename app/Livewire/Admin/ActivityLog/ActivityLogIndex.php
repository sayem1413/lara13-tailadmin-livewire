<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class ActivityLogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $event = '';

    #[Url]
    public string $subjectType = '';

    public function mount(): void
    {
        Gate::authorize('admin.activity-log.index');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function eventOptions(): Collection
    {
        return Activity::query()
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');
    }

    /**
     * @return Collection<string, string>
     */
    #[Computed]
    public function subjectTypeOptions(): Collection
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type) => [$type => class_basename($type)])
            ->sort();
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    protected function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('description', 'like', "%{$this->search}%")
                        ->orWhereHasMorph('causer', [User::class], function (Builder $query) {
                            $query->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->event !== '', fn (Builder $query) => $query->where('event', $this->event))
            ->when($this->subjectType !== '', fn (Builder $query) => $query->where('subject_type', $this->subjectType))
            ->latest()
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.admin.activity-log.activity-log-index', [
            'activities' => $this->activities(),
        ]);
    }
}
