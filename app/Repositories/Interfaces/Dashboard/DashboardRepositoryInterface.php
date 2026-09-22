<?php

namespace App\Repositories\Interfaces\Dashboard;

interface DashboardRepositoryInterface
{
    public function totalUsers(): int;

    public function totalRoles(): int;
}
