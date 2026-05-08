<?php

namespace App\Http\Requests\Admin;

use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    /**
     * Whitelist of subject_type values the modal exposes as filter options.
     * Keys are the short names the frontend sends; values are the FQCNs stored
     * in `audit_logs.subject_type`. Polymorphic morph map is intentionally not
     * configured app-wide (FQCNs everywhere), so this map is the single place
     * the short names are translated.
     *
     * @var array<string, class-string>
     */
    public const SUBJECT_TYPES = [
        'Application' => Application::class,
        'ApplicationDocument' => ApplicationDocument::class,
        'StudentProfile' => StudentProfile::class,
        'User' => User::class,
        'Role' => Role::class,
        'Department' => Department::class,
        'ProgramOffering' => ProgramOffering::class,
        'DocumentType' => DocumentType::class,
        'LevelCredentialRequirement' => LevelCredentialRequirement::class,
    ];

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', Rule::in(array_column(AuditAction::cases(), 'value'))],
            'subject_types' => ['nullable', 'array'],
            'subject_types.*' => ['string', Rule::in(array_keys(self::SUBJECT_TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'rows' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<class-string>
     */
    public function subjectFqcns(): array
    {
        $shortNames = $this->input('subject_types', []);

        return collect($shortNames)
            ->map(fn (string $name): string => self::SUBJECT_TYPES[$name])
            ->values()
            ->all();
    }
}
