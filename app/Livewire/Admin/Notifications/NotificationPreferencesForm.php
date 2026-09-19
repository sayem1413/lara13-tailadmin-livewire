<?php

namespace App\Livewire\Admin\Notifications;

use App\Services\Notification\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class NotificationPreferencesForm extends Component
{
    /** @var array<string, array<string, bool>> */
    public array $values = [];

    public function mount(NotificationService $notifications): void
    {
        Gate::authorize('admin.notifications.preferences.edit');

        foreach ($notifications->preferencesFor(auth()->user()) as $type => $preference) {
            $this->values[$type] = $preference['channels'];
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach (array_keys($this->values) as $type) {
            foreach (array_keys($this->values[$type]) as $channel) {
                $rules["values.{$type}.{$channel}"] = ['boolean'];
            }
        }

        return $rules;
    }

    public function save(NotificationService $notifications): void
    {
        Gate::authorize('admin.notifications.preferences.edit');

        $validated = $this->validate();

        $notifications->updatePreferences(auth()->user(), $validated['values']);

        $this->dispatch('toast', type: 'success', message: 'Notification preferences updated.');
    }

    public function render(NotificationService $notifications): View
    {
        return view('livewire.admin.notifications.notification-preferences-form', [
            'schema' => $notifications->preferencesFor(auth()->user()),
        ]);
    }
}
