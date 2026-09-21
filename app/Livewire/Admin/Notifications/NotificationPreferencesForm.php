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
     * Built from config/notification_types.php rather than array_keys($this->values)
     * - $values is a public property Livewire hydrates from client-supplied
     * request data on every request, so deriving the allowed keys from it
     * would let a tampered payload validate its own injected type/channel
     * keys instead of being restricted to the real schema.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach (config('notification_types', []) as $type => $definition) {
            foreach (array_keys($definition['channels']) as $channel) {
                $rules["values.{$type}.{$channel}"] = ['boolean'];
            }
        }

        return $rules;
    }

    public function save(NotificationService $notifications): void
    {
        Gate::authorize('admin.notifications.preferences.edit');

        $validated = $this->validate();

        $notifications->updatePreferences(auth()->user(), $validated['values'] ?? []);

        $this->dispatch('toast', type: 'success', message: 'Notification preferences updated.');
    }

    public function render(NotificationService $notifications): View
    {
        return view('livewire.admin.notifications.notification-preferences-form', [
            'schema' => $notifications->preferencesFor(auth()->user()),
        ]);
    }
}
