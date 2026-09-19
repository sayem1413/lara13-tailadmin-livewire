<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function edit(): View
    {
        Gate::authorize('admin.notifications.preferences.edit');

        return view('admin.notifications.preferences');
    }

    public function update(UpdateNotificationPreferenceRequest $request): RedirectResponse
    {
        Gate::authorize('admin.notifications.preferences.edit');

        $this->notificationService->updatePreferences(
            $request->user(),
            $request->validated()['values'] ?? []
        );

        return redirect()->route('admin.notifications.preferences.edit')->with('success', 'Notification preferences updated.');
    }
}
