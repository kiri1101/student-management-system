<?php

use App\Models\Transcript;
use App\Services\TranscriptPdfRenderer;

it('renders a transcript to PDF bytes', function (): void {
    $transcript = Transcript::factory()->create();

    expect(app(TranscriptPdfRenderer::class)->render($transcript))->toStartWith('%PDF');
});

it('embeds the verify URL and transcript number in the transcript view', function (): void {
    $transcript = Transcript::factory()->create();

    $html = view('pdf.transcript', [
        'transcript' => $transcript,
        'snapshot' => $transcript->snapshot,
        'verifyUrl' => route('transcripts.verify', $transcript->transcript_number),
        'qrSvg' => '<svg></svg>',
    ])->render();

    expect($html)->toContain($transcript->transcript_number)
        ->and($html)->toContain('transcripts/verify');
});
