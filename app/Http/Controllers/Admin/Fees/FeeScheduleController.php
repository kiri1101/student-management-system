<?php

namespace App\Http\Controllers\Admin\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Fees\FeeScheduleStoreRequest;
use App\Http\Requests\Admin\Fees\FeeScheduleUpdateRequest;
use App\Models\FeeSchedule;
use App\Models\ProgramOffering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FeeScheduleController extends Controller
{
    /**
     * List fee schedules with their offering, department, and installments,
     * optionally including trashed rows so they can be restored (AUD-021).
     */
    public function index(Request $request): Response
    {
        $withTrashed = $request->boolean('trashed');

        return Inertia::render('admin/fees/Index', [
            'schedules' => FeeSchedule::query()
                ->when($withTrashed, fn ($query) => $query->withTrashed())
                ->with([
                    'programOffering:id,department_id,degree_program,min_level,max_level',
                    'programOffering.department:id,name,code',
                    'installments:id,fee_schedule_id,sequence,label,amount_xaf,due_date',
                ])
                ->orderByDesc('academic_year')
                ->orderBy('program_offering_id')
                ->orderBy('level')
                ->get(),
            'filters' => ['trashed' => $withTrashed],
            'offerings' => ProgramOffering::query()
                ->with('department:id,name,code')
                ->orderBy('department_id')
                ->orderBy('degree_program')
                ->get(['id', 'department_id', 'degree_program', 'min_level', 'max_level']),
        ]);
    }

    /**
     * Store a new fee schedule together with its installment set.
     */
    public function store(FeeScheduleStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $schedule = FeeSchedule::create($request->safe()->only([
                'program_offering_id', 'level', 'academic_year', 'total_xaf',
            ]));

            $this->syncInstallments($schedule, $request->validated('installments', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee schedule created.')]);

        return back();
    }

    /**
     * Update a fee schedule and replace its installment set.
     */
    public function update(FeeScheduleUpdateRequest $request, FeeSchedule $feeSchedule): RedirectResponse
    {
        DB::transaction(function () use ($request, $feeSchedule): void {
            $feeSchedule->update($request->safe()->only([
                'program_offering_id', 'level', 'academic_year', 'total_xaf',
            ]));

            $this->syncInstallments($feeSchedule, $request->validated('installments', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee schedule updated.')]);

        return back();
    }

    /**
     * Soft-delete a fee schedule. Its installments are left in place and return
     * with it on restore; they are only removed on a hard delete (cascade).
     */
    public function destroy(FeeSchedule $feeSchedule): RedirectResponse
    {
        $feeSchedule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee schedule deleted.')]);

        return back();
    }

    /**
     * Restore a soft-deleted schedule. Refuses while its offering is trashed
     * (the row would 500 every consumer). The (offering, level, year) unique
     * index spans trashed rows, so no key-conflict guard is needed.
     */
    public function restore(FeeSchedule $feeSchedule): RedirectResponse
    {
        if (! $feeSchedule->trashed()) {
            return back();
        }

        if (! $feeSchedule->programOffering()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot restore: this schedule\'s program offering is deleted. Restore the offering first.'),
            ]);

            return back();
        }

        $feeSchedule->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fee schedule restored.')]);

        return back();
    }

    /**
     * Replace the schedule's installments with the validated payload. Installments
     * are a managed set, not independently mutable rows, so the simplest correct
     * sync is delete-and-recreate inside the caller's transaction.
     *
     * @param  array<int, array{sequence: int, label: string, amount_xaf: int, due_date: string}>  $installments
     */
    private function syncInstallments(FeeSchedule $schedule, array $installments): void
    {
        $schedule->installments()->delete();

        if ($installments === []) {
            return;
        }

        $schedule->installments()->createMany($installments);
    }
}
