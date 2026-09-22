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

it("builds an 'in' rule from a select field's configured options", function () {
    config(['settings' => [
        'general' => ['label' => 'General', 'fields' => [
            'theme' => ['type' => 'select', 'label' => 'Theme', 'options' => ['light' => 'Light', 'dark' => 'Dark']],
        ]],
    ]]);

    expect(app(SettingService::class)->validationRules())->toBe([
        'values.theme' => ['nullable', 'string', 'in:light,dark'],
    ]);
});

it('throws a clear configuration error for a select field declared with no options, instead of silently rejecting every submission', function () {
    config(['settings' => [
        'general' => ['label' => 'General', 'fields' => [
            'theme' => ['type' => 'select', 'label' => 'Theme'],
        ]],
    ]]);

    expect(fn () => app(SettingService::class)->validationRules())
        ->toThrow(InvalidArgumentException::class, 'theme');
});
