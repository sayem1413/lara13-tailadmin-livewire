<?php

use App\Http\Middleware\EnsureApplicationIsNotInMaintenanceMode;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\FrameGuard;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'maintenance' => EnsureApplicationIsNotInMaintenanceMode::class,
        ]);

        // Laravel's default 'web' group doesn't set X-Frame-Options -
        // without it, an authenticated admin page can be framed on an
        // external page and its buttons clickjacked into an unintended
        // action.
        $middleware->web(append: [FrameGuard::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // config/activitylog.php's clean_after_days only takes effect once
        // this command actually runs - without a schedule, activity_log
        // grows without bound in production.
        $schedule->command('activitylog:clean')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
