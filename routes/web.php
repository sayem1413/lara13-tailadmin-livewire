<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Authentication routes (login, forgot/reset password, ...) are registered
| by Laravel Fortify - see FortifyServiceProvider. Everything below is the
| authenticated dashboard shell and its admin modules, gated by the "auth"
| and "active" middleware.
|
*/

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::get('/password', 'password')->name('password');
    });

    /*
    |----------------------------------------------------------------------
    | Admin Modules
    |----------------------------------------------------------------------
    |
    | Each module is gated by its own Spatie permission (see the Policies
    | in app/Policies and RolesAndPermissionsSeeder) and only appears in
    | the sidebar - see config/menu.php - once the signed-in user is
    | authorized for it.
    |
    */

    Route::name('admin.')->group(function () {

        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::get('/{user}/edit', 'edit')->name('edit');
        });

        Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::get('/{role}/edit', 'edit')->name('edit');
        });

        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');

    });

});
