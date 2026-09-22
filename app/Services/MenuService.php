<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class MenuService
{
    /**
     * Build the sidebar menu for the current user: config/menu.php filtered
     * down to the groups/items they're permitted to see, in display order.
     *
     * @return array<int, array{group: string, items: Collection<int, array<string, mixed>>}>
     */
    public function forSidebar(): array
    {
        /** @var array<int, array<string, mixed>> $groups */
        $groups = config('menu', []);

        return collect($groups)
            ->map(fn (array $group) => [
                'group' => $group['group'],
                'items' => $this->filterItems($group['items'] ?? []),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterItems(array $items): Collection
    {
        return collect($items)
            ->sortBy('order')
            ->map(function (array $item) {
                if (isset($item['children'])) {
                    $item['children'] = $this->filterItems($item['children'])->values()->all();
                }

                return $item;
            })
            ->filter(fn (array $item) => $this->isVisible($item))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isVisible(array $item): bool
    {
        if (isset($item['children'])) {
            return count($item['children']) > 0;
        }

        if (empty($item['route']) || ! Route::has($item['route'])) {
            return false;
        }

        return $this->userCanSee($item['permission'] ?? null);
    }

    /**
     * @param  array<int, string>|string|null  $permission
     */
    protected function userCanSee(string|array|null $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return collect(Arr::wrap($permission))->contains(fn (string $p) => $user->can($p));
    }
}
