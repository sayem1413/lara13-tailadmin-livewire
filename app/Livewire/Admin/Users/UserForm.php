<?php

namespace App\Livewire\Admin\Users;

use App\Models\Permission\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UserForm extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public bool $is_active = true;

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            Gate::authorize('update', $user);

            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_active = $user->is_active;
            $this->selectedRoles = $user->roles->pluck('name')->all();
        } else {
            Gate::authorize('create', User::class);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'password' => [$this->userId ? 'nullable' : 'required', 'confirmed', Password::default()],
            'is_active' => ['boolean'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function save(): void
    {
        $user = $this->userId ? User::findOrFail($this->userId) : new User;

        Gate::authorize($this->userId ? 'update' : 'create', $this->userId ? $user : User::class);

        $validated = $this->validate();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        // Filter against assignableRoles() rather than trusting the submitted
        // array outright: a tampered request could include "Super Admin"
        // even though the rendered checkboxes never offer it.
        $user->syncRoles(collect($this->selectedRoles)->intersect($this->assignableRoles())->all());

        session()->flash('success', $this->userId ? 'User updated.' : 'User created.');

        $this->redirect(route('admin.users.index'));
    }

    /**
     * @return array<int, string>
     */
    protected function assignableRoles(): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name');

        if (! auth()->user()->hasRole('Super Admin')) {
            $roles = $roles->reject(fn (string $name) => $name === 'Super Admin');
        }

        return $roles->values()->all();
    }

    public function render(): View
    {
        return view('livewire.admin.users.user-form', [
            'availableRoles' => $this->assignableRoles(),
        ]);
    }
}
