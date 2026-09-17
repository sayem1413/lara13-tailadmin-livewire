<?php

use App\Services\SettingService;

it('returns the given default when a setting has not been set', function () {
    expect(app(SettingService::class)->get('app_name', 'Fallback'))->toBe('Fallback');
});

it('persists and retrieves a setting value', function () {
    $service = app(SettingService::class);

    $service->set('app_name', 'Acme Inc');

    expect($service->get('app_name'))->toBe('Acme Inc');
});

it('reflects a value updated after it was already cached', function () {
    $service = app(SettingService::class);

    $service->set('app_name', 'First');
    expect($service->get('app_name'))->toBe('First');

    $service->set('app_name', 'Second');

    expect($service->get('app_name'))->toBe('Second');
});
