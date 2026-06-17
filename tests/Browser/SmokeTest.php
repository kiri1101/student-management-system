<?php

use App\Enums\RoleName;
use App\Models\User;

/**
 * Minimal Pest 4 browser smoke suite (AUD-029). These cover the JS-heavy
 * surfaces that HTTP-level feature tests cannot exercise — the same class of
 * client-side regression that AUD-015/016 slipped through. Keep it small and
 * fast; deep interaction coverage stays in the HTTP feature suite.
 */
it('renders the login page without JavaScript errors and signs a user in', function () {
    $user = User::factory()->create();

    $page = visit('/login');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Log in')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();
});

it('renders the applicant application form without JavaScript errors', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/application/new');

    // The form is now a 4-step wizard; step 1 (Programme) is what renders first.
    $page->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertSee('New application')
        ->assertSee('Degree programme');
});

it('opens the admin audit-log modal without JavaScript errors', function () {
    $this->actingAs(userWithRole(RoleName::Admin));

    $page = visit('/admin/dashboard');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Administrator')
        ->click('Open audit log')
        ->assertSee('Actor')
        ->assertNoJavaScriptErrors();
});
