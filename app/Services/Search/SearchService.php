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
     * allowed to see, and whose route currently exists, returning one group
     * per module with at least one match - ready for the header search
     * dropdown to render under a section heading per module.
     *
     * @return Collection<int, array{module: string, results: Collection<int, array{title: mixed, description: mixed, url: string}>}>
     */
    public function search(?User $user, string $term, int $perModuleLimit = 5, int $totalGroupLimit = 5): Collection
    {
        // GlobalSearch only stops calling this at 2 characters (see its
        // results() computed property) - nothing caps the upper end, and
        // the term is bound straight into a LIKE '%...%' per configured
        // module below, so an arbitrarily long value would otherwise be
        // run against every permitted module's query as-is.
        $term = mb_substr($term, 0, 255);

        /** @var array<int, array<string, mixed>> $modules */
        $modules = config('search.modules', []);

        return collect($modules)
            ->filter(fn (array $module) => empty($module['permission']) || $user?->can($module['permission']))
            ->filter(fn (array $module) => Route::has($module['route'] ?? ''))
            ->map(fn (array $module) => [
                'module' => (string) $module['label'],
                'results' => $this->searchModule($module, $term, $perModuleLimit),
            ])
            ->filter(fn (array $group) => $group['results']->isNotEmpty())
            ->take($totalGroupLimit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $module
     * @return Collection<int, array{title: mixed, description: mixed, url: string}>
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
                'title' => data_get($record, $module['title']),
                'description' => data_get($record, $module['description'] ?? null),
                'url' => route($module['route'], [$module['route_param'] => $record->getKey()]),
            ]);
    }
}
