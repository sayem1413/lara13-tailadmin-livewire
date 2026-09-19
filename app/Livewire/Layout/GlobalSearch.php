<?php

namespace App\Livewire\Layout;

use App\Services\Search\SearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

        return app(SearchService::class)->search($user, $term);
    }

    public function render(): View
    {
        return view('livewire.layout.global-search');
    }
}
