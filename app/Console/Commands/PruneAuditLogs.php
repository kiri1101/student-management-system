<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('audit:prune {--days= : Retention horizon in days (defaults to AuditLog::RETENTION_DAYS)}')]
#[Description('Delete audit_logs rows older than the retention horizon. Uses the query builder because the AuditLog model intentionally blocks Eloquent deletes.')]
class PruneAuditLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? AuditLog::RETENTION_DAYS);

        if ($days < 1) {
            $this->error('Retention horizon must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = DB::table((new AuditLog)->getTable())
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'Pruned %d audit log %s older than %s (%d-day retention).',
            $deleted,
            str('record')->plural($deleted),
            $cutoff->toDateTimeString(),
            $days,
        ));

        return self::SUCCESS;
    }
}
