<?php

namespace App\Services\Dashboard;

use App\Repositories\Interfaces\Dashboard\DashboardRepositoryInterface;

class DashboardService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository
    ) {}

    /**
     * @return array{userCount: int, roleCount: int}
     */
    public function summary(): array
    {
        return [
            'userCount' => $this->dashboardRepository->totalUsers(),
            'roleCount' => $this->dashboardRepository->totalRoles(),
        ];
    }
}
