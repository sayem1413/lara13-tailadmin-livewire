<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create');
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', ['role' => $role]);
    }
}
