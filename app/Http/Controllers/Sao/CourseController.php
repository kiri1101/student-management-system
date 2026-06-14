<?php

namespace App\Http\Controllers\Sao;

use App\Actions\Sao\ReviewCoursePlanApproval;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sao\AssignLecturerRequest;
use App\Http\Requests\Sao\RejectCoursePlanRequest;
use App\Http\Requests\Sao\StoreCourseRequest;
use App\Http\Requests\Sao\UpdateCourseRequest;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\LecturerProfile;
use App\Models\ProgramOffering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(private readonly ReviewCoursePlanApproval $reviewCoursePlan) {}

    /**
     * List courses for the catalog, optionally filtered by academic year and
     * plan status. Each row carries a denormalized offering label and the
     * assigned lecturer's name for display.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'academic_year' => $request->query('academic_year'),
            'plan_status' => $request->query('plan_status'),
        ];

        $courses = Course::query()
            ->with([
                'programOffering.department:id,name,code',
                'lecturer.user:id,name',
            ])
            ->when($filters['academic_year'], fn ($query, $year) => $query->where('academic_year', $year))
            ->when($filters['plan_status'], fn ($query, $status) => $query->where('plan_status', $status))
            ->orderByDesc('academic_year')
            ->orderBy('program_offering_id')
            ->orderBy('level')
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'credits' => $course->credits,
                'semester' => $course->semester,
                'level' => $course->level,
                'academic_year' => $course->academic_year,
                'plan_status' => $course->plan_status->value,
                'offering_label' => $this->offeringLabel($course->programOffering),
                'lecturer_name' => $course->lecturer?->user?->name,
            ])
            ->all();

        return Inertia::render('sao/courses/Index', [
            'courses' => $courses,
            'filters' => $filters,
            'lecturers' => $this->lecturerOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sao/courses/Form', [
            'course' => null,
            'programOfferings' => $this->programOfferingOptions(),
            'lecturers' => $this->lecturerOptions(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $course = Course::create($request->validated());

        AuditLog::record(AuditAction::CourseCreated, $course, ['attributes' => $course->auditAttributes()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course created.')]);

        return to_route('sao.courses.index');
    }

    public function edit(Course $course): Response
    {
        return Inertia::render('sao/courses/Form', [
            'course' => $this->coursePayload($course),
            'programOfferings' => $this->programOfferingOptions(),
            'lecturers' => $this->lecturerOptions(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course updated.')]);

        return to_route('sao.courses.index');
    }

    public function assignLecturer(AssignLecturerRequest $request, Course $course): RedirectResponse
    {
        $course->update(['lecturer_profile_id' => $request->integer('lecturer_profile_id')]);

        AuditLog::record(
            AuditAction::LecturerAssigned,
            $course,
            ['lecturer_profile_id' => $course->lecturer_profile_id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lecturer assigned.')]);

        return back();
    }

    public function approve(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('approve-course-plan');

        $this->reviewCoursePlan->approve($course, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan approved.')]);

        return back();
    }

    public function reject(RejectCoursePlanRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('approve-course-plan');

        $this->reviewCoursePlan->reject($course, $request->user(), $request->string('notes')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan rejected.')]);

        return back();
    }

    /**
     * Full course payload for the edit form.
     *
     * @return array<string, mixed>
     */
    private function coursePayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'program_offering_id' => $course->program_offering_id,
            'level' => $course->level,
            'academic_year' => $course->academic_year,
            'code' => $course->code,
            'title' => $course->title,
            'credits' => $course->credits,
            'semester' => $course->semester,
            'description' => $course->description,
            'lecturer_profile_id' => $course->lecturer_profile_id,
            'plan_status' => $course->plan_status->value,
        ];
    }

    /**
     * Selectable program offerings with their level bounds (drives the level
     * field's min/max in the form).
     *
     * @return array<int, array{id: int, label: string, min_level: int, max_level: int}>
     */
    private function programOfferingOptions(): array
    {
        return ProgramOffering::query()
            ->with('department:id,name,code')
            ->orderBy('department_id')
            ->orderBy('degree_program')
            ->get()
            ->map(fn (ProgramOffering $offering): array => [
                'id' => $offering->id,
                'label' => $this->offeringLabel($offering),
                'min_level' => $offering->min_level,
                'max_level' => $offering->max_level,
            ])
            ->all();
    }

    /**
     * Selectable lecturers, labeled by their user name.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function lecturerOptions(): array
    {
        return LecturerProfile::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (LecturerProfile $lecturer): array => [
                'id' => $lecturer->id,
                'name' => $lecturer->user?->name ?? __('Unknown lecturer'),
            ])
            ->all();
    }

    /**
     * Build a human-readable offering label from its department + degree program,
     * mirroring how the admin fee screens present an offering.
     */
    private function offeringLabel(?ProgramOffering $offering): string
    {
        if ($offering === null) {
            return __('Unknown offering');
        }

        $department = $offering->department?->name ?? __('Unknown department');
        $degree = Str::title($offering->degree_program->value);

        return "{$department} — {$degree}";
    }
}
