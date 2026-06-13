<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * A valid bcrypt hash of a random throwaway string. Checked on the
     * identifier-not-found path so a login attempt costs one hash check
     * whether or not the identifier resolves to an account — response time
     * stops being an enumeration oracle (AUDIT.md AUD-025).
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$rdMMsXLxqb1k99.LLdvlru6MPu6kPltiXsZ0n7kKnUK/Q2FkELia6';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->throttleFortifyRoutes();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure the identifier-flexible login resolver.
     *
     * Accepts the configured username field as `email`, `users.employee_id`,
     * or `student_profiles.matricule`.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = (string) $request->input(Fortify::username());
            $password = (string) $request->input('password');

            // One query across the three identifier namespaces (AUD-025);
            // they can't collide — emails carry an `@`, the other two don't.
            $user = User::query()
                ->where(function (Builder $query) use ($identifier): void {
                    $query->where('email', $identifier)
                        ->orWhere('employee_id', $identifier)
                        ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->where('matricule', $identifier));
                })
                ->first();

            if ($user === null) {
                Hash::check($password, self::DUMMY_PASSWORD_HASH);

                return null;
            }

            return Hash::check($password, $user->password) ? $user : null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(3)->by($throttleKey);
        });

        // Referenced by `fortify.limiters.verification` (notification send
        // and link click-through).
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        // AUD-026: authenticated-but-unthrottled JSON lookups and the document
        // download. Generous per-user ceilings that stop load amplification
        // without touching normal UI flows (cascading dropdowns, file fetches).
        RateLimiter::for('lookups', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        // AUD-026: tighter limiter for the admin audit-log modal endpoint —
        // it reads a growing table (AUD-008), so it gets a smaller budget.
        RateLimiter::for('audit-logs', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }

    /**
     * Attach throttle middleware to the Fortify routes that have no native
     * limiter hook: registration, reset-link requests, and the reset itself
     * (AUDIT.md AUD-011). Runs after boot so the routes exist.
     */
    private function throttleFortifyRoutes(): void
    {
        $this->app->booted(function (): void {
            $routes = Route::getRoutes();
            // The name table may predate Fortify's chained ->name() calls.
            $routes->refreshNameLookups();

            $routes->getByName('register.store')?->middleware('throttle:register');
            $routes->getByName('password.email')?->middleware('throttle:forgot-password');
            $routes->getByName('password.update')?->middleware('throttle:forgot-password');
        });
    }
}
