<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'request' => $request,
        ]));

        // Fortify's login/forgot-password/reset-password routes - and our
        // own guest-only "/" route - are all wrapped in the "guest"
        // middleware, which redirects an already-authenticated visitor
        // away. Its default target only knows routes literally named
        // "dashboard" or "home", neither of which exists once the
        // dashboard route is namespaced under "admin.".
        RedirectIfAuthenticated::redirectUsing(fn () => route('admin.dashboard.index'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('reset-password', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Fortify's own routes.php hardcodes only "guest" middleware on
        // "password.email"/"password.update" - unlike "login", it offers no
        // config-driven limiter for them, so the throttle has to be layered
        // onto the already-registered routes here instead. This must wait
        // until every provider - including Fortify's - has booted, and
        // look the routes up by iterating (not RouteCollection::getByName(),
        // whose name index is built when a route is added to the
        // collection, before its later ->name() call takes effect).
        $this->app->booted(function () {
            $routeNames = ['password.email', 'password.update'];

            foreach (Route::getRoutes()->getRoutes() as $route) {
                if (in_array($route->getName(), $routeNames, true)) {
                    $route->middleware('throttle:reset-password');
                }
            }
        });
    }
}
