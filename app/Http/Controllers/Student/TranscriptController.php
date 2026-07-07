<?php

namespace App\Http\Controllers\Student;

use App\Actions\IssueTranscript;
use App\Http\Controllers\Controller;
use App\Services\TranscriptPdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TranscriptController extends Controller
{
    /**
     * Stream the authenticated student's own official transcript as a PDF,
     * aggregating all their published results. Redirects back with a notice when
     * the student has no published results yet (no empty document is issued).
     */
    public function download(Request $request, IssueTranscript $issueTranscript, TranscriptPdfRenderer $renderer): Response
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You need an active student enrollment to generate a transcript.')]);

            return back();
        }

        $transcript = $issueTranscript->execute($profile, $request->user(), 'student');

        if ($transcript === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No published results yet — your transcript will be available once results are published.')]);

            return back();
        }

        return response($renderer->render($transcript), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$transcript->transcript_number.'.pdf"',
        ]);
    }
}
