<?php

namespace App\Livewire\Admin\Settings;

use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SettingsForm extends Component
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(SettingService $settings): void
    {
        Gate::authorize('admin.settings.edit');

        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $key => $field) {
                $this->values[$key] = $settings->get($key, $field['default'] ?? null);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $key => $field) {
                $rules["values.{$key}"] = match ($field['type']) {
                    'boolean' => ['boolean'],
                    'select' => ['nullable', 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                    default => ['nullable', 'string', 'max:2000'],
                };
            }
        }

        return $rules;
    }

    public function save(SettingService $settings): void
    {
        Gate::authorize('admin.settings.update');

        $validated = $this->validate();

        $settings->setMany($validated['values']);

        session()->flash('success', 'Settings updated.');
    }

    /**
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    protected function schema(): array
    {
        /** @var array<string, array{label: string, fields: array<string, array<string, mixed>>}> $schema */
        $schema = config('settings', []);

        return $schema;
    }

    public function render(): View
    {
        return view('livewire.admin.settings.settings-form', [
            'schema' => $this->schema(),
        ]);
    }
}
