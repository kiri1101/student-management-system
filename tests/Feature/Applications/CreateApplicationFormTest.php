<?php

use App\Models\User;
use Database\Seeders\DemoReferencesSeeder;
use Database\Seeders\DocumentTypesSeeder;

beforeEach(function (): void {
    $this->seed(DocumentTypesSeeder::class);
    $this->seed(DemoReferencesSeeder::class);
});

it('renders the application form with reference props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('application.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('applicant/applications/Create')
        ->has('degreePrograms', 3)
        ->has('departments')
        ->has('alwaysRequiredDocumentTypes', 2)
        ->where('alwaysRequiredDocumentTypes.0.code', 'BIRTH')
        ->where('alwaysRequiredDocumentTypes.1.code', 'NID'));
});

it('blocks guests from the application form', function () {
    $response = $this->get(route('application.create'));

    $response->assertRedirect(route('login'));
});

it('blocks unverified users from the application form', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('application.create'));

    $response->assertRedirect(route('verification.notice'));
});
