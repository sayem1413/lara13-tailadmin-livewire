<?php

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Models\Setting;

it('mass-assigns Role fillable fields but silently discards a non-fillable id', function () {
    $role = Role::create([
        'id' => 999999,
        'name' => 'Editor',
        'guard_name' => 'web',
        'description' => 'Can edit content',
        'is_active' => false,
    ]);

    expect($role->id)->not->toBe(999999)
        ->and($role->name)->toBe('Editor')
        ->and($role->guard_name)->toBe('web')
        ->and($role->description)->toBe('Can edit content')
        ->and($role->is_active)->toBeFalse();
});

it('mass-assigns Permission fillable fields but silently discards a non-fillable id', function () {
    $permission = Permission::create([
        'id' => 888888,
        'name' => 'admin.widgets.index',
        'guard_name' => 'web',
        'module' => 'widgets',
        'section' => 'index',
        'description' => 'View widgets',
    ]);

    expect($permission->id)->not->toBe(888888)
        ->and($permission->name)->toBe('admin.widgets.index')
        ->and($permission->guard_name)->toBe('web')
        ->and($permission->module)->toBe('widgets')
        ->and($permission->section)->toBe('index')
        ->and($permission->description)->toBe('View widgets');
});

it('mass-assigns Setting fillable fields but silently discards a non-fillable id', function () {
    $setting = Setting::create([
        'id' => 777777,
        'key' => 'custom_key',
        'value' => 'custom value',
    ]);

    expect($setting->id)->not->toBe(777777)
        ->and($setting->key)->toBe('custom_key')
        ->and($setting->value)->toBe('custom value');
});
