<?php

namespace App\Http\Middleware;

use App\Services\Setting\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsNotInMaintenanceMode
{
    /**
     * The Settings page's "Maintenance Mode" toggle previously had no
     * effect anywhere in the app. Once enabled, this blocks the
     * authenticated dashboard for anyone who can't reach the Settings page
     * to turn it back off, so the toggle actually does what its label says
     * without locking every admin out.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app(SettingService::class)->get('maintenance_mode', false) && Gate::denies('admin.settings.edit')) {
            abort(503, __('The application is currently undergoing maintenance. Please check back shortly.'));
        }

        return $next($request);
    }
}
