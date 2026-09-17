<?php

use App\Services\SettingService;

if (! function_exists('setting')) {
    /**
     * Get or set an application setting.
     *
     * Call with no arguments to get the SettingService instance,
     * with one argument to read a value, or with two to write one.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingService::class);

        if (is_null($key)) {
            return $service;
        }

        return $service->get($key, $default);
    }
}
