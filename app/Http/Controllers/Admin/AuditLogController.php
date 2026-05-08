<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * @return array{}|array<string, mixed>
     */
    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $sortOrder = $request->input('sort_order', 'desc');
        $rows = (int) $request->input('rows', 25);

        $paginator = AuditLog::query()
            ->with('user:id,name,email')
            ->when(
                $request->filled('user_id'),
                fn ($query) => $query->where('user_id', $request->integer('user_id')),
            )
            ->when(
                $request->filled('actions'),
                fn ($query) => $query->whereIn('action', $request->input('actions')),
            )
            ->when(
                $request->filled('subject_types'),
                fn ($query) => $query->whereIn('subject_type', $request->subjectFqcns()),
            )
            ->when(
                $request->filled('from'),
                fn ($query) => $query->where('occurred_at', '>=', $request->date('from')->startOfDay()),
            )
            ->when(
                $request->filled('to'),
                fn ($query) => $query->where('occurred_at', '<=', $request->date('to')->endOfDay()),
            )
            ->orderBy('occurred_at', $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate($rows)
            ->withQueryString();

        $paginator->getCollection()->transform(fn (AuditLog $log): array => [
            'id' => $log->id,
            'action' => $log->action->value,
            'subject_type' => self::shortSubjectName($log->subject_type),
            'subject_id' => $log->subject_id,
            'changes' => $log->changes,
            'context' => $log->context,
            'occurred_at' => $log->occurred_at->toIso8601String(),
            'user' => $log->user === null ? null : [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ],
        ]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'options' => [
                'actions' => collect(AuditAction::cases())
                    ->map(fn (AuditAction $action): array => [
                        'value' => $action->value,
                        'label' => $action->name,
                    ])
                    ->values()
                    ->all(),
                'subject_types' => collect(array_keys(AuditLogIndexRequest::SUBJECT_TYPES))
                    ->map(fn (string $name): array => [
                        'value' => $name,
                        'label' => $name,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Translate a stored FQCN back to the short name the frontend uses.
     * Returns null for null subject_type rows (e.g. anonymous LoginFailed).
     */
    private static function shortSubjectName(?string $fqcn): ?string
    {
        if ($fqcn === null) {
            return null;
        }

        $map = array_flip(AuditLogIndexRequest::SUBJECT_TYPES);

        return $map[$fqcn] ?? class_basename($fqcn);
    }
}
