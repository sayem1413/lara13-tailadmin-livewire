<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\NotificationPreferenceController;
use App\Http\Controllers\Admin\ProductController;
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

Route::middleware(['auth', 'active', 'maintenance'])->name('admin.')->group(function () {

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

    // withTrashed() lets implicit route-model-binding resolve a soft-deleted
    // {user} - without it, Laravel's default binding query excludes
    // trashed rows and both routes would 404 before reaching the
    // controller.
    Route::put('/users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
    Route::delete('/users/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete')->withTrashed();

    Route::resource('roles', RoleController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);

    Route::resource('categories', CategoryController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);
    Route::put('/categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();
    Route::delete('/categories/{category}/force-delete', [CategoryController::class, 'forceDelete'])->name('categories.force-delete')->withTrashed();

    Route::resource('products', ProductController::class)->only([
        'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
    ]);
    Route::put('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore')->withTrashed();
    Route::delete('/products/{product}/force-delete', [ProductController::class, 'forceDelete'])->name('products.force-delete')->withTrashed();

    // Real named routes (not Livewire-only) so admin.inventory.adjust is
    // discovered by SyncPermissionsFromRoutes the same way every other
    // permission in this app is - see the comment on the resource
    // controllers above.
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    Route::resource('media', MediaController::class)->only(['index', 'destroy']);

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Sits under the already-ignored "admin.notifications.*" wildcard in
    // SyncPermissionsFromRoutes (the plain notifications index has no
    // permission of its own), so this permission is seeded manually in
    // RolesAndPermissionsSeeder instead of being auto-discovered.
    Route::prefix('notifications/preferences')->name('notifications.preferences.')->controller(NotificationPreferenceController::class)->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
    });

});
