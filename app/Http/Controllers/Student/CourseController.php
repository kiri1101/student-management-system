<?php

namespace App\Http\Controllers\Student;

use App\Enums\CoursePlanStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * The authenticated student's course list: every approved course in their
     * implicit cohort (offering + level + academic_year), ordered by semester
     * then code so the page can group courses per semester. Reuses the exact
     * cohort query of the sibling attendance/assignments/results screens —
     * only approved plans are visible to students.
     */
    public function index(Request $request): Response
    {
        $profile = $request->user()->studentProfile()
            ->with('programOffering.department:id,name,code')
            ->first();

        $courses = [];

        if ($profile !== null) {
            $courses = Course::query()
                ->where('program_offering_id', $profile->program_offering_id)
                ->where('level', $profile->level)
                ->where('academic_year', $profile->academic_year)
                ->where('plan_status', CoursePlanStatus::Approved->value)
                ->with('lecturer.user:id,name')
                ->withCount(['sessions', 'assignments'])
                ->orderBy('semester')
                ->orderBy('code')
                ->get()
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'credits' => $course->credits,
                    'semester' => $course->semester,
                    'description' => $course->description,
                    'lecturer_name' => $course->lecturer?->user?->name,
                    'sessions_count' => $course->sessions_count,
                    'assignments_count' => $course->assignments_count,
                ])
                ->all();
        }

        return Inertia::render('student/courses/Index', [
            'courses' => $courses,
            'cohort' => $profile === null ? null : [
                'level' => $profile->level,
                'academic_year' => $profile->academic_year,
                'program_offering' => $profile->programOffering === null ? null : [
                    'degree_program' => $profile->programOffering->degree_program->value,
                    'department' => $profile->programOffering->department === null ? null : [
                        'name' => $profile->programOffering->department->name,
                        'code' => $profile->programOffering->department->code,
                    ],
                ],
            ],
        ]);
    }
}
