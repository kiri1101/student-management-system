<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Map of ability name => roles allowed to perform it.
     *
     * @var array<string, array<int, RoleName>>
     */
    protected const ABILITIES = [
        'process-admission' => [RoleName::Sao, RoleName::Admin],
        'decide-application' => [RoleName::Sao, RoleName::Admin],
        'validate-payment' => [RoleName::Accountant, RoleName::Admin],
        'publish-results' => [RoleName::Sao, RoleName::Admin],
        'approve-course-plan' => [RoleName::Sao, RoleName::Admin],
        'mark-attendance' => [RoleName::Lecturer, RoleName::Admin],
        'view-audit-log' => [RoleName::Admin],
        'manage-references' => [RoleName::Admin],
    ];

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
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Define the top-level authorization gates for role-based abilities.
     */
    protected function configureGates(): void
    {
        foreach (self::ABILITIES as $ability => $roles) {
            Gate::define($ability, fn (User $user): bool => $user->hasAnyRole($roles));
        }
    }
}
