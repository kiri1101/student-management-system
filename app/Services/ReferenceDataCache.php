<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache for the rarely-changing reference data the applicant
 * cascading-dropdown endpoints depend on (departments, program offerings,
 * required-document rules, always-required document types).
 *
 * The cache is store-agnostic: it uses a fixed, enumerable set of keys with
 * explicit invalidation — no cache *tags* — so it behaves identically on every
 * store (file/redis/array), including the `array` store used in tests. The four
 * admin reference controllers call {@see flush()} after any write (issue #39);
 * the TTL bounds staleness from any out-of-band change that skips a controller.
 */
final class ReferenceDataCache
{
    /**
     * Time-to-live, in seconds, applied to every cached entry as a safety net
     * against an out-of-band write that skips {@see flush()}. Normal writes
     * invalidate immediately through the reference controllers, so this only
     * bounds staleness from seeders/tinker/raw SQL — one day is ample.
     */
    private const TTL = 86400;

    private const KEY_DEPARTMENTS = 'reference.departments';

    private const KEY_OFFERINGS = 'reference.offerings';

    private const KEY_PROTECTED_DOCUMENT_TYPES = 'reference.document_types.protected';

    private const KEY_LEVEL_REQUIREMENTS = 'reference.level_requirements';

    /**
     * Every key this cache owns, so {@see flush()} can clear them without tags.
     *
     * @var array<int, string>
     */
    private const ALL_KEYS = [
        self::KEY_DEPARTMENTS,
        self::KEY_OFFERINGS,
        self::KEY_PROTECTED_DOCUMENT_TYPES,
        self::KEY_LEVEL_REQUIREMENTS,
    ];

    /**
     * All departments, ordered by name.
     *
     * @return array<int, array{id: int, name: string, code: string}>
     */
    public function departments(): array
    {
        return Cache::remember(self::KEY_DEPARTMENTS, self::TTL, fn (): array => Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ])
            ->all());
    }

    /**
     * Every program offering with its department, in the shape the cascading
     * dropdown expects. Callers filter the returned list in memory.
     *
     * @return array<int, array<string, mixed>>
     */
    public function offerings(): array
    {
        return Cache::remember(self::KEY_OFFERINGS, self::TTL, fn (): array => ProgramOffering::query()
            ->with('department:id,name,code')
            ->orderBy('department_id')
            ->orderBy('degree_program')
            ->get(['id', 'department_id', 'degree_program', 'min_level', 'max_level'])
            ->map(fn (ProgramOffering $offering): array => [
                'id' => $offering->id,
                'department_id' => $offering->department_id,
                'department' => [
                    'id' => $offering->department->id,
                    'name' => $offering->department->name,
                    'code' => $offering->department->code,
                ],
                'degree_program' => $offering->degree_program->value,
                'min_level' => $offering->min_level,
                'max_level' => $offering->max_level,
            ])
            ->all());
    }

    /**
     * Always-required document types (NID/BIRTH), ordered by code.
     *
     * @return array<int, array{id: int, name: string, code: string}>
     */
    public function protectedDocumentTypes(): array
    {
        return Cache::remember(self::KEY_PROTECTED_DOCUMENT_TYPES, self::TTL, fn (): array => DocumentType::query()
            ->whereIn('code', DocumentType::PROTECTED_CODES)
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (DocumentType $documentType): array => [
                'id' => $documentType->id,
                'name' => $documentType->name,
                'code' => $documentType->code,
            ])
            ->all());
    }

    /**
     * Required document rules grouped by program offering id. Callers select
     * their offering, then filter the entries by level in memory. Offerings
     * with no required rules are simply absent from the map.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function levelRequirements(): array
    {
        return Cache::remember(self::KEY_LEVEL_REQUIREMENTS, self::TTL, fn (): array => LevelCredentialRequirement::query()
            ->where('required', true)
            ->with('documentType:id,name,code')
            ->orderBy('level')
            ->orderBy('id')
            ->get()
            ->groupBy('program_offering_id')
            ->map(fn ($rules): array => $rules
                ->map(fn (LevelCredentialRequirement $rule): array => [
                    'id' => $rule->id,
                    'level' => $rule->level,
                    'required' => $rule->required,
                    'document_type' => [
                        'id' => $rule->documentType->id,
                        'name' => $rule->documentType->name,
                        'code' => $rule->documentType->code,
                    ],
                ])
                ->values()
                ->all())
            ->all());
    }

    /**
     * Forget every cached reference entry. Called by the admin reference
     * controllers after any create/update/delete/restore, and by the database
     * seeder once reference rows have been (re)seeded.
     */
    public function flush(): void
    {
        foreach (self::ALL_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
