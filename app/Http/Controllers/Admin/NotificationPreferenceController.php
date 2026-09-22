<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            $this->notificationService->updatePreferences(
                $request->user(),
                $request->validated()['values'] ?? []
            );
        } catch (Throwable $exception) {
            Log::error('Failed to save notification preferences.', ['exception' => $exception]);

            return redirect()->route('admin.notifications.preferences.edit')->with('error', 'Something went wrong while saving your notification preferences. Please try again.');
        }

        return redirect()->route('admin.notifications.preferences.edit')->with('success', 'Notification preferences updated.');
    }
}
