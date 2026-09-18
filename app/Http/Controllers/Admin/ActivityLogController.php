<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('admin.activity-log.index');

        return view('admin.activity-log.index');
    }
}
