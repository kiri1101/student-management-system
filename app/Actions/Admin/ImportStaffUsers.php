<?php

namespace App\Actions\Admin;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use SplFileObject;

class ImportStaffUsers
{
    /**
     * Maximum number of data rows accepted in a single import. Keeps the
     * preview/commit request bounded and the per-row create loop predictable.
     */
    public const MAX_ROWS = 500;

    /**
     * Header columns recognised in the CSV, in canonical (template) order.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'name',
        'email',
        'role',
        'employee_id',
        'department_code',
        'specialization',
        'hired_at',
        'bank_desk',
        'cashier_window',
        'scope',
    ];

    /**
     * Headers that must be present for the file to be parseable at all.
     *
     * @var list<string>
     */
    private const REQUIRED_HEADERS = ['name', 'email', 'role'];

    /**
     * Role of the row currently being mapped, so the conditional
     * department_code rule can read it via the closure during validation.
     */
    private ?string $currentRole = null;

    public function __construct(private readonly CreateUserAction $createUser) {}

    /**
     * Parse and validate a staff CSV without writing anything. Returns a
     * per-row preview alongside a roll-up summary.
     *
     * @return array{
     *     fatal: string|null,
     *     rows: list<array{line: int, data: array<string, string|null>, status: 'ok'|'error', errors: list<string>, department_id?: int|null}>,
     *     summary: array{total: int, valid: int, invalid: int},
     * }
     */
    public function validate(UploadedFile $file): array
    {
        $handle = new SplFileObject($file->getRealPath(), 'r');
        $handle->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $headerMap = [];
        $rows = [];

        $departmentMap = $this->departmentCodeMap();

        $seenEmails = [];
        $seenEmployeeIds = [];

        $valid = 0;
        $invalid = 0;
        $dataRowCount = 0;

        foreach ($handle as $lineIndex => $record) {
            // SplFileObject yields a `[null]` row for a trailing newline; skip it.
            if ($record === [null] || $record === false) {
                continue;
            }

            // CSV line number is 1-based: header is line 1, first data row line 2.
            $line = $lineIndex + 1;

            if ($header === null) {
                $header = $this->normalizeHeader($record);

                $missing = array_diff(self::REQUIRED_HEADERS, $header);

                if ($missing !== []) {
                    return $this->fatal(sprintf(
                        'The CSV is missing required column(s): %s. Required headers are: %s.',
                        implode(', ', $missing),
                        implode(', ', self::REQUIRED_HEADERS),
                    ));
                }

                $headerMap = array_flip($header);

                continue;
            }

            $dataRowCount++;

            if ($dataRowCount > self::MAX_ROWS) {
                return $this->fatal(sprintf(
                    'The import is limited to %d rows per file. Please split the file and try again.',
                    self::MAX_ROWS,
                ));
            }

            $data = $this->mapRow($record, $headerMap);

            [$status, $errors, $departmentId] = $this->validateRow(
                $data,
                $departmentMap,
                $seenEmails,
                $seenEmployeeIds,
            );

            if ($data['email'] !== null && $data['email'] !== '') {
                $seenEmails[mb_strtolower($data['email'])] = $line;
            }

            if ($data['employee_id'] !== null && $data['employee_id'] !== '') {
                $seenEmployeeIds[$data['employee_id']] = $line;
            }

            if ($status === 'ok') {
                $valid++;
            } else {
                $invalid++;
            }

            $rows[] = [
                'line' => $line,
                'data' => $data,
                'status' => $status,
                'errors' => $errors,
                'department_id' => $departmentId,
            ];
        }

        if ($header === null) {
            return $this->fatal(sprintf(
                'The CSV appears to be empty. Required headers are: %s.',
                implode(', ', self::REQUIRED_HEADERS),
            ));
        }

        return [
            'fatal' => null,
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => $invalid,
            ],
        ];
    }

    /**
     * Re-validate the uploaded file (stateless re-validate-on-commit) and
     * create every valid row through the audited single-user create path.
     *
     * @return array{
     *     fatal: string|null,
     *     imported: list<array{line: int, name: string, email: string, role: string}>,
     *     skipped: list<array{line: int, data: array<string, string|null>, errors: list<string>}>,
     *     summary: array{total: int, imported: int, skipped: int},
     * }
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $preview = $this->validate($file);

        if ($preview['fatal'] !== null) {
            return [
                'fatal' => $preview['fatal'],
                'imported' => [],
                'skipped' => [],
                'summary' => ['total' => 0, 'imported' => 0, 'skipped' => 0],
            ];
        }

        $imported = [];
        $skipped = [];

        /** @var array<string, int> $roleCounts */
        $roleCounts = [];

        foreach ($preview['rows'] as $row) {
            if ($row['status'] !== 'ok') {
                $skipped[] = [
                    'line' => $row['line'],
                    'data' => $row['data'],
                    'errors' => $row['errors'],
                ];

                continue;
            }

            $role = RoleName::from((string) $row['data']['role']);

            $this->createUser->execute([
                'name' => (string) $row['data']['name'],
                'email' => (string) $row['data']['email'],
                'role' => $role,
                'employee_id' => $this->blankToNull($row['data']['employee_id']),
                'profile' => $this->profilePayload($role, $row['data'], $row['department_id'] ?? null),
            ]);

            $roleCounts[$role->value] = ($roleCounts[$role->value] ?? 0) + 1;

            $imported[] = [
                'line' => $row['line'],
                'name' => (string) $row['data']['name'],
                'email' => (string) $row['data']['email'],
                'role' => $role->value,
            ];
        }

        if ($imported !== []) {
            AuditLog::record(
                AuditAction::UsersImported,
                $actor,
                context: [
                    'count' => count($imported),
                    'roles' => $roleCounts,
                ],
            );
        }

        return [
            'fatal' => null,
            'imported' => $imported,
            'skipped' => $skipped,
            'summary' => [
                'total' => count($preview['rows']),
                'imported' => count($imported),
                'skipped' => count($skipped),
            ],
        ];
    }

    /**
     * Validate a single mapped row, layering in-file uniqueness and department
     * resolution on top of the field-level rules mirrored from StoreUserRequest.
     *
     * @param  array<string, string|null>  $data
     * @param  array<string, int>  $departmentMap  lowercased code => department id
     * @param  array<string, int>  $seenEmails  lowercased email => first line seen
     * @param  array<string, int>  $seenEmployeeIds  employee_id => first line seen
     * @return array{0: 'ok'|'error', 1: list<string>, 2: int|null}
     */
    private function validateRow(
        array $data,
        array $departmentMap,
        array $seenEmails,
        array $seenEmployeeIds,
    ): array {
        $validator = Validator::make($data, $this->rowRules(), $this->rowMessages());

        $errors = array_values($validator->errors()->all());

        // In-file uniqueness (Validator only sees one row at a time).
        $email = $data['email'] !== null && $data['email'] !== '' ? mb_strtolower($data['email']) : null;
        if ($email !== null && isset($seenEmails[$email])) {
            $errors[] = 'Duplicate email within file.';
        }

        $employeeId = $this->blankToNull($data['employee_id']);
        if ($employeeId !== null && isset($seenEmployeeIds[$employeeId])) {
            $errors[] = 'Duplicate employee ID within file.';
        }

        // Resolve department for lecturer rows.
        $departmentId = null;
        $isLecturer = $data['role'] === RoleName::Lecturer->value;
        $departmentCode = $this->blankToNull($data['department_code']);

        if ($isLecturer && $departmentCode !== null) {
            $departmentId = $departmentMap[$departmentCode] ?? null;

            if ($departmentId === null) {
                $errors[] = sprintf("Unknown department code '%s'.", $departmentCode);
            }
        }

        return [$errors === [] ? 'ok' : 'error', $errors, $departmentId];
    }

    /**
     * Field-level rules mirroring StoreUserRequest::rules() for a flat row.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rowRules(): array
    {
        $roleValues = array_map(
            fn (RoleName $role): string => $role->value,
            StoreUserRequest::CREATABLE_ROLES,
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Includes soft-deleted rows on purpose (Rule::unique queries the
                // raw table) — matches StoreUserRequest semantics.
                Rule::unique(User::class, 'email'),
            ],
            'role' => ['required', 'string', Rule::in($roleValues)],
            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                StoreUserRequest::EMPLOYEE_ID_REGEX,
                Rule::unique(User::class, 'employee_id'),
            ],
            'department_code' => [
                Rule::requiredIf(fn (): bool => ($this->currentRole ?? null) === RoleName::Lecturer->value),
                'nullable',
                'string',
                'max:255',
            ],
            'specialization' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'bank_desk' => ['nullable', 'string', 'max:255'],
            'cashier_window' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function rowMessages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists (it may be a deactivated account).',
            'employee_id.unique' => 'This employee ID is already assigned to another user.',
            'employee_id.regex' => 'Employee IDs may only contain letters, digits, dots, dashes and underscores, and may not start with "stm-".',
            'department_code.required' => 'A department_code is required for lecturer rows.',
        ];
    }

    /**
     * Build the per-role profile payload, filtering empty values the same way
     * StoreUserRequest::profilePayload() does.
     *
     * @param  array<string, string|null>  $data
     * @return array<string, mixed>
     */
    private function profilePayload(RoleName $role, array $data, ?int $departmentId): array
    {
        $filter = fn ($value): bool => $value !== null && $value !== '';

        return match ($role) {
            RoleName::Lecturer => array_filter([
                'department_id' => $departmentId,
                'specialization' => $this->blankToNull($data['specialization']),
                'hired_at' => $this->blankToNull($data['hired_at']),
            ], $filter),
            RoleName::Accountant => array_filter([
                'bank_desk' => $this->blankToNull($data['bank_desk']),
                'cashier_window' => $this->blankToNull($data['cashier_window']),
            ], $filter),
            RoleName::Sao => array_filter([
                'scope' => $this->blankToNull($data['scope']),
            ], $filter),
            default => [],
        };
    }

    /**
     * Normalize header cells: strip a UTF-8 BOM from the first cell, trim, and
     * lowercase every name.
     *
     * @param  list<string|null>  $record
     * @return list<string>
     */
    private function normalizeHeader(array $record): array
    {
        $header = [];

        foreach (array_values($record) as $index => $cell) {
            $value = (string) $cell;

            if ($index === 0) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            }

            $header[] = mb_strtolower(trim($value));
        }

        return $header;
    }

    /**
     * Map a raw CSV record onto the canonical flat field set by header name,
     * trimming values and lowercasing role / employee_id / department_code for
     * matching. Returns every known column (missing columns become null).
     *
     * @param  list<string|null>  $record
     * @param  array<string, int>  $headerMap  header name => column index
     * @return array<string, string|null>
     */
    private function mapRow(array $record, array $headerMap): array
    {
        $values = array_values($record);
        $data = [];

        foreach (self::COLUMNS as $column) {
            $index = $headerMap[$column] ?? null;
            $raw = $index !== null && array_key_exists($index, $values) ? $values[$index] : null;
            $value = $raw === null ? null : trim((string) $raw);

            if ($value === '') {
                $value = null;
            }

            if ($value !== null && in_array($column, ['role', 'employee_id', 'department_code'], true)) {
                $value = mb_strtolower($value);
            }

            $data[$column] = $value;
        }

        // Cache role for the conditional department_code rule closure.
        $this->currentRole = $data['role'];

        return $data;
    }

    /**
     * Preload departments into a lowercased `code => id` map.
     *
     * @return array<string, int>
     */
    private function departmentCodeMap(): array
    {
        return Department::query()
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Department $department): array => [
                mb_strtolower((string) $department->code) => (int) $department->id,
            ])
            ->all();
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{fatal: string, rows: array<int, never>, summary: array{total: 0, valid: 0, invalid: 0}}
     */
    private function fatal(string $message): array
    {
        return [
            'fatal' => $message,
            'rows' => [],
            'summary' => ['total' => 0, 'valid' => 0, 'invalid' => 0],
        ];
    }
}
