<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\DisputeStatus;
use App\Models\AuditLog;
use App\Models\ResultDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewResultDispute
{
    /**
     * Advance a dispute to UnderReview, Resolved or Rejected. Mirrors the hardened
     * review pattern: the dispute is re-fetched under a row lock and re-guarded as
     * non-terminal inside the transaction so a concurrent review can't re-decide a
     * settled dispute. A terminal outcome stamps the reviewer, timestamp and notes;
     * UnderReview only records the (optional) notes.
     *
     * @throws ValidationException
     */
    public function review(ResultDispute $dispute, DisputeStatus $status, ?string $notes, User $reviewer): ResultDispute
    {
        return DB::transaction(function () use ($dispute, $status, $notes, $reviewer): ResultDispute {
            $dispute = ResultDispute::query()
                ->whereKey($dispute->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispute->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('This dispute has already been resolved.'),
                ]);
            }

            if ($status->isTerminal()) {
                $dispute->fill([
                    'status' => $status,
                    'resolution_notes' => $notes,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ]);
            } else {
                $dispute->fill([
                    'status' => $status,
                    'resolution_notes' => $notes,
                ]);
            }

            $dispute->save();

            if ($status->isTerminal()) {
                AuditLog::record(
                    AuditAction::DisputeResolved,
                    $dispute,
                    ['status' => $status->value],
                    userId: $reviewer->id,
                );
            }

            return $dispute;
        });
    }
}
