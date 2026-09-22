<?php

namespace App\Repositories\Eloquent\Dashboard;

use App\Models\Permission\Role;
use App\Models\User;
use App\Repositories\Interfaces\Dashboard\DashboardRepositoryInterface;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        protected User $userModel,
        protected Role $roleModel
    ) {}

    public function totalUsers(): int
    {
        return $this->userModel->query()->count();
    }

    public function totalRoles(): int
    {
        return $this->roleModel->query()->count();
    }
}
