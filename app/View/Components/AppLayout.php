<?php

namespace App\View\Components;

use App\Services\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class AppLayout extends Component
{
    /**
     * @var array<int, array{group: string, items: Collection<int, array<string, mixed>>}>
     */
    public array $menu;

    public function __construct(public ?string $title = null, ?MenuService $menuService = null)
    {
        $this->menu = ($menuService ?? app(MenuService::class))->forSidebar();
    }

    public function render(): View
    {
        return view('components.app-layout');
    }
}
