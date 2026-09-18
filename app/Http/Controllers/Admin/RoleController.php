<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Permission\Role;
use App\Services\Role\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

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

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $this->roleService->createRole($request->validated());

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);

        return view('admin.roles.show', ['role' => $role]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', ['role' => $role]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $this->roleService->updateRole($role, $request->validated());

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roleService->deleteRole($role);

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
}
