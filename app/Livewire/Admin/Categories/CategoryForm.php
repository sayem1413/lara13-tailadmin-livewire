<?php

namespace App\Livewire\Admin\Categories;

use App\Exceptions\Lifecycle\LifecycleGuardException;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CategoryForm extends Component
{
    #[Locked]
    public ?int $categoryId = null;

    public ?int $parent_id = null;

    public string $name = '';

    public string $slug = '';

    public ?string $description = null;

    public int $sort_order = 0;

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            Gate::authorize('update', $category);

            $this->categoryId = $category->id;
            $this->parent_id = $category->parent_id;
            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->description = $category->description;
            $this->sort_order = $category->sort_order;
        } else {
            Gate::authorize('create', Category::class);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                Rule::notIn([$this->categoryId]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->categoryId)->withoutTrashed()],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function updatedName(): void
    {
        if ($this->categoryId === null && $this->slug === '') {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $category = $this->categoryId ? app(CategoryService::class)->findOrFail($this->categoryId) : null;

        Gate::authorize($this->categoryId ? 'update' : 'create', $category ?? Category::class);

        $validated = $this->validate();

        try {
            if ($category) {
                app(CategoryService::class)->updateCategory($category, $validated);
            } else {
                app(CategoryService::class)->createCategory($validated);
            }
        } catch (LifecycleGuardException $exception) {
            $this->addError('parent_id', $exception->getMessage());

            return;
        }

        session()->flash('success', $this->categoryId ? 'Category updated.' : 'Category created.');

        $this->redirect(route('admin.categories.index'));
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    protected function parentOptions(): Collection
    {
        $category = $this->categoryId ? Category::find($this->categoryId) : null;

        return app(CategoryService::class)->selectOptions($category);
    }

    public function render(): View
    {
        return view('livewire.admin.categories.category-form', [
            'parentOptions' => $this->parentOptions(),
        ]);
    }
}
