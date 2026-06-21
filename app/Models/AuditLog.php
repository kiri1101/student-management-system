<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Append-only audit trail. Records are immutable once written — the `booted`
 * guards reject Eloquent updates and deletes — so pruning past RETENTION_DAYS
 * happens via the query builder in the `audit:prune` command. Most rows are
 * written automatically by the RecordsAudit trait; AuditLog::record() handles
 * non-CRUD domain events.
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'changes', 'context', 'occurred_at'])]
class AuditLog extends Model
{
    /**
     * Default retention horizon for audit records (2 years). Rows older than
     * this are removed by the scheduled `audit:prune` command, which deletes
     * via the query builder because this model blocks Eloquent deletes.
     */
    public const RETENTION_DAYS = 730;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changes' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('AuditLog records are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('AuditLog records are immutable and cannot be deleted.');
        });
    }

    /**
     * The actor who triggered the entry, or null for system/unauthenticated events.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model the entry is about (polymorphic; null for subject-less events).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an audit entry for an arbitrary action.
     *
     * @param  array<string, mixed>|null  $changes
     * @param  array<string, mixed>  $context
     */
    public static function record(
        AuditAction $action,
        ?Model $subject = null,
        ?array $changes = null,
        array $context = [],
        ?int $userId = null,
    ): self {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'context' => self::buildContext($context),
            'occurred_at' => Carbon::now(),
        ]);
    }

    /**
     * Merge ip / user agent / route name from the current request into the
     * caller-supplied context. Caller-supplied keys win on conflict.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function buildContext(array $extra = []): array
    {
        $context = [];

        if (app()->bound('request')) {
            $request = request();
            $ip = $request->ip();
            $userAgent = $request->userAgent();
            $routeName = $request->route()?->getName();

            if ($ip !== null) {
                $context['ip'] = $ip;
            }
            if ($userAgent !== null && $userAgent !== '') {
                $context['user_agent'] = $userAgent;
            }
            if ($routeName !== null) {
                $context['route'] = $routeName;
            }
        }

        return $extra + $context;
    }
}
