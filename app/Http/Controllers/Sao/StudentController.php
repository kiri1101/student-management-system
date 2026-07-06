<?php

namespace App\Http\Controllers\Sao;

use App\Actions\IssueTranscript;
use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Services\TranscriptPdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    /**
     * Searchable, paginated list of student profiles for staff — the home for
     * looking up any student and generating their transcript.
     */
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));

        $students = StudentProfile::query()
            ->with(['user:id,name', 'programOffering.department:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('matricule', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('matricule')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentProfile $profile): array => [
                'id' => $profile->id,
                'matricule' => $profile->matricule,
                'name' => $profile->user?->name,
                'programme' => $profile->programOffering?->department?->name,
                'level' => $profile->level,
                'status' => $profile->status->value,
            ]);

        return Inertia::render('sao/students/Index', [
            'students' => $students,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Stream any student's official transcript as a PDF (staff-issued). Redirects
     * back with a notice when the student has no published results.
     */
    public function transcript(Request $request, StudentProfile $studentProfile, IssueTranscript $issueTranscript, TranscriptPdfRenderer $renderer): Response
    {
        $transcript = $issueTranscript->execute($studentProfile, $request->user(), 'sao');

        if ($transcript === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This student has no published results yet.')]);

            return back();
        }

        return response($renderer->render($transcript), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$transcript->transcript_number.'.pdf"',
        ]);
    }
}
