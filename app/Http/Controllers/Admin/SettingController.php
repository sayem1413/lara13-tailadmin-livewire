<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('admin.settings.edit');

        return view('admin.settings.edit');
    }
}
