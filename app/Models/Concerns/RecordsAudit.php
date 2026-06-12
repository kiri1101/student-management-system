<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;

trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        static::created(function ($model): void {
            AuditLog::record(
                AuditAction::Created,
                $model,
                ['attributes' => $model->auditAttributes()],
            );
        });

        static::updated(function ($model): void {
            $diff = $model->auditDiff();

            if ($diff === null) {
                return;
            }

            AuditLog::record(
                AuditAction::Updated,
                $model,
                $diff,
            );
        });

        static::deleted(function ($model): void {
            AuditLog::record(
                AuditAction::Deleted,
                $model,
                ['attributes' => $model->auditAttributes()],
            );
        });

        static::restored(function ($model): void {
            AuditLog::record(
                AuditAction::Restored,
                $model,
                ['attributes' => $model->auditAttributes()],
            );
        });
    }

    /**
     * Attribute names to omit from audit snapshots and diffs entirely —
     * housekeeping fields whose changes carry no audit value.
     *
     * @return array<int, string>
     */
    public function auditExclude(): array
    {
        return [
            'remember_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Attribute names whose *changes* matter (a password change is an audit
     * event) but whose values must never reach the log. Diffs record them as
     * "[redacted]"; snapshots omit them.
     *
     * @return array<int, string>
     */
    public function auditRedact(): array
    {
        return [
            'password',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];
    }

    /**
     * Snapshot of the model's persisted attributes, with sensitive and
     * housekeeping fields stripped.
     *
     * @return array<string, mixed>
     */
    public function auditAttributes(): array
    {
        return collect($this->getAttributes())
            ->except([...$this->auditExclude(), ...$this->auditRedact()])
            ->all();
    }

    /**
     * Before/after diff for the keys that changed during the most recent save.
     * Returns null when the only changes were excluded fields (e.g. timestamps).
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}|null
     */
    public function auditDiff(): ?array
    {
        $excluded = $this->auditExclude();
        $redacted = $this->auditRedact();
        $diff = ['before' => [], 'after' => []];

        foreach ($this->getChanges() as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (in_array($key, $redacted, true)) {
                $diff['before'][$key] = '[redacted]';
                $diff['after'][$key] = '[redacted]';

                continue;
            }

            $diff['before'][$key] = $this->getOriginal($key);
            $diff['after'][$key] = $value;
        }

        return $diff['after'] === [] ? null : $diff;
    }
}
