<?php

namespace App\Livewire\Admin\Settings;

use App\Services\Setting\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class SettingsForm extends Component
{
    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(SettingService $settings): void
    {
        Gate::authorize('admin.settings.edit');

        foreach ($settings->schema() as $group) {
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
        return app(SettingService::class)->validationRules();
    }

    public function save(SettingService $settings): void
    {
        Gate::authorize('admin.settings.update');

        $validated = $this->validate();

        try {
            $settings->setMany($validated['values']);
        } catch (Throwable $exception) {
            Log::error('Failed to save settings.', ['exception' => $exception]);

            $this->dispatch('toast', type: 'error', message: 'Something went wrong while saving settings. Please try again.');

            return;
        }

        // save() doesn't redirect - it re-renders this same page - so a
        // session flash would never be seen: nothing triggers a fresh page
        // load for x-app-layout to pick it up. Dispatching a browser event
        // instead delivers the toast immediately, in this same response.
        $this->dispatch('toast', type: 'success', message: 'Settings updated.');
    }

    public function render(SettingService $settings): View
    {
        return view('livewire.admin.settings.settings-form', [
            'schema' => $settings->schema(),
        ]);
    }
}
