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

Route::middleware('guest')->get('/', fn () => view('auth.login'));

Route::middleware(['auth', 'active'])->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

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
    | Each module below is gated by its own Spatie permission (see the
    | Policies in app/Policies and RolesAndPermissionsSeeder) and only
    | appears in the sidebar - see config/menu.php - once the signed-in
    | user is authorized for it. Dashboard/notifications/profile above
    | have no permission of their own - every active user can reach them -
    | which is why they're excluded in SyncPermissionsFromRoutes.
    |
    */

    // Full resource controllers (not just the index/create/edit pages the
    // Livewire components need) so the same Service/Repository layer for
    // each module is ready to back a v2 build for another platform later,
    // without the UI here needing to change.
    Route::resource('users', UserController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);

    Route::resource('roles', RoleController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);

    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

});
