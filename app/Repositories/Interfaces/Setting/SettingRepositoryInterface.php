<?php

namespace App\Repositories\Interfaces\Setting;

interface SettingRepositoryInterface
{
    /**
     * Every stored setting value, keyed by its setting key.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    public function set(string $key, mixed $value): void;
}
