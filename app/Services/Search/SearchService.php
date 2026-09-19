<?php

namespace App\Services\Search;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class SearchService
{
    /**
     * Search every registered module (config/search.php) the given user is
     * allowed to see, and whose route currently exists, returning grouped
     * result rows ready for the header search dropdown.
     *
     * @return Collection<int, array{module: string, title: mixed, description: mixed, url: string}>
     */
    public function search(?User $user, string $term, int $perModuleLimit = 5, int $totalLimit = 10): Collection
    {
        /** @var array<int, array<string, mixed>> $modules */
        $modules = config('search.modules', []);

        return collect($modules)
            ->filter(fn (array $module) => empty($module['permission']) || $user?->can($module['permission']))
            ->filter(fn (array $module) => Route::has($module['route'] ?? ''))
            ->flatMap(fn (array $module) => $this->searchModule($module, $term, $perModuleLimit))
            ->take($totalLimit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $module
     * @return Collection<int, array{module: string, title: mixed, description: mixed, url: string}>
     */
    protected function searchModule(array $module, string $term, int $limit): Collection
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
            ->limit($limit)
            ->get()
            ->map(fn ($record) => [
                'module' => (string) $module['label'],
                'title' => data_get($record, $module['title']),
                'description' => data_get($record, $module['description'] ?? null),
                'url' => route($module['route'], [$module['route_param'] => $record->getKey()]),
            ]);
    }
}
