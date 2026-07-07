<?php

use App\Models\Transcript;
use Illuminate\Support\Facades\DB;

it('is immutable — updates and deletes throw', function (): void {
    $transcript = Transcript::factory()->create();

    expect(fn () => $transcript->update(['cgpa' => 1.0]))->toThrow(RuntimeException::class)
        ->and(fn () => $transcript->delete())->toThrow(RuntimeException::class);
});

it('issues sequential per-year transcript numbers', function (): void {
    expect(Transcript::nextTranscriptNumberForYear(2026))->toBe('TRN-2026-00001')
        ->and(Transcript::nextTranscriptNumberForYear(2026))->toBe('TRN-2026-00002')
        ->and(Transcript::nextTranscriptNumberForYear(2027))->toBe('TRN-2027-00001');
});

it('verifies an untampered record and rejects a tampered snapshot', function (): void {
    $transcript = Transcript::factory()->create();

    expect($transcript->verifies())->toBeTrue();

    // Tamper the stored snapshot directly, bypassing the immutable model.
    DB::table('transcripts')->where('id', $transcript->id)->update([
        'snapshot' => json_encode(['student' => ['matricule' => 'hacked'], 'semesters' => [], 'cumulative' => []]),
    ]);

    expect($transcript->fresh()->verifies())->toBeFalse();
});
