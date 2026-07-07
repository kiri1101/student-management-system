<?php

use App\Models\Transcript;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('shows the summary for an authentic transcript', function (): void {
    $transcript = Transcript::factory()->create();

    $this->get(route('transcripts.verify', $transcript->transcript_number))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transcripts/Verify')
            ->where('valid', true)
            ->where('transcript.transcript_number', $transcript->transcript_number));
});

it('reads invalid for a tampered snapshot without leaking data', function (): void {
    $transcript = Transcript::factory()->create();
    DB::table('transcripts')->where('id', $transcript->id)->update(['snapshot' => json_encode(['semesters' => []])]);

    $this->get(route('transcripts.verify', $transcript->transcript_number))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false)->where('transcript', null));
});

it('reads invalid for an unknown number', function (): void {
    $this->get(route('transcripts.verify', 'TRN-2099-99999'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false)->where('transcript', null));
});
