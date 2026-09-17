<?php

namespace App\Livewire\Layout;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    /**
     * @return Collection<int, array{module: string, title: mixed, description: mixed, url: string}>
     */
    #[Computed]
    public function results(): Collection
    {
        $term = trim($this->query);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $user = auth()->user();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = config('search.modules', []);

        return collect($modules)
            ->filter(fn (array $module) => empty($module['permission']) || $user?->can($module['permission']))
            ->filter(fn (array $module) => Route::has($module['route'] ?? ''))
            ->flatMap(fn (array $module) => $this->searchModule($module, $term))
            ->take(10)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $module
     * @return Collection<int, array{module: string, title: mixed, description: mixed, url: string}>
     */
    protected function searchModule(array $module, string $term): Collection
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $module['model'];
        $columns = $module['columns'];

        return $modelClass::query()
            ->where(function ($query) use ($columns, $term) {
                foreach ($columns as $index => $column) {
                    $index === 0
                        ? $query->where($column, 'like', "%{$term}%")
                        : $query->orWhere($column, 'like', "%{$term}%");
                }
            })
            ->limit(5)
            ->get()
            ->map(fn ($record) => [
                'module' => (string) $module['label'],
                'title' => data_get($record, $module['title']),
                'description' => data_get($record, $module['description'] ?? null),
                'url' => route($module['route'], [$module['route_param'] => $record->getKey()]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.layout.global-search');
    }
}
