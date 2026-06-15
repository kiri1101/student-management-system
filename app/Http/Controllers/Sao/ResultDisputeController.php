<?php

namespace App\Http\Controllers\Sao;

use App\Actions\ReviewResultDispute;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewResultDisputeRequest;
use App\Models\ResultDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultDisputeController extends Controller
{
    /**
     * The result-dispute queue, newest first, optionally filtered by status. Each
     * row denormalizes the disputed result's marks and the student/course it
     * belongs to. The sao.php route group already enforces role:sao,admin.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $disputes = ResultDispute::query()
            ->with([
                'courseResult.course:id,code,title',
                'studentProfile.user:id,name',
                'reviewer:id,name',
            ])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ResultDispute $dispute): array {
                $result = $dispute->courseResult;

                return [
                    'id' => $dispute->id,
                    'status' => $dispute->status->value,
                    'reason' => $dispute->reason,
                    'resolution_notes' => $dispute->resolution_notes,
                    'created_at' => $dispute->created_at->toIso8601String(),
                    'reviewed_at' => $dispute->reviewed_at?->toIso8601String(),
                    'reviewer_name' => $dispute->reviewer?->name,
                    'student_name' => $dispute->studentProfile?->user?->name,
                    'matricule' => $dispute->studentProfile?->matricule,
                    'course_code' => $result?->course?->code,
                    'course_title' => $result?->course?->title,
                    'ca_score' => $result?->ca_score,
                    'exam_score' => $result?->exam_score,
                    'final_score' => $result?->final_score,
                    'grade' => $result?->grade,
                ];
            })
            ->all();

        return Inertia::render('sao/disputes/Index', [
            'disputes' => $disputes,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function review(ReviewResultDisputeRequest $request, ResultDispute $dispute, ReviewResultDispute $action): RedirectResponse
    {
        $action->review($dispute, $request->disputeStatus(), $request->resolutionNotes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dispute updated.')]);

        return back();
    }
}
