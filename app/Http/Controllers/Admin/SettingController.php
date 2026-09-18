<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function edit(): View
    {
        Gate::authorize('admin.settings.edit');

        return view('admin.settings.edit');
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        Gate::authorize('admin.settings.update');

        $this->settingService->setMany($request->validated()['values'] ?? []);

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated successfully.');
    }
}
