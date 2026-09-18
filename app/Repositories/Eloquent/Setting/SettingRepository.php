<?php

namespace App\Repositories\Eloquent\Setting;

use App\Models\Setting;
use App\Repositories\Interfaces\Setting\SettingRepositoryInterface;

class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        protected Setting $model
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->model->query()->pluck('value', 'key')->all();
    }

    public function set(string $key, mixed $value): void
    {
        $this->model->query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
