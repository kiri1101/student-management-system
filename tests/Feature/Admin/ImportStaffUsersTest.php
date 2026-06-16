<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Mail\UserInvitationMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
    $this->withoutVite();
});

/*
|--------------------------------------------------------------------------
| Helpers (feature-prefixed: importStaff*)
|--------------------------------------------------------------------------
*/

/**
 * The canonical CSV header line the importer expects, in column order.
 */
function importStaffHeader(): string
{
    return 'name,email,role,employee_id,department_code,specialization,hired_at,bank_desk,cashier_window,scope';
}

/**
 * Build a fake CSV upload from a header line + N data lines joined with "\n".
 *
 * @param  array<int, string>  $dataLines
 */
function importStaffCsv(array $dataLines, ?string $header = null): UploadedFile
{
    $header ??= importStaffHeader();
    $content = implode("\n", array_merge([$header], $dataLines))."\n";

    return UploadedFile::fake()->createWithContent('staff.csv', $content);
}

/**
 * Seed a Department with a known code the lecturer CSV rows can resolve.
 */
function importStaffDepartment(string $code = 'CS', string $name = 'Computer Science'): Department
{
    return Department::firstOrCreate(['code' => $code], ['name' => $name]);
}

/*
|--------------------------------------------------------------------------
| 1. Preview writes nothing
|--------------------------------------------------------------------------
*/

it('previews an all-valid CSV without writing users or queuing mail', function () {
    Mail::fake();

    $before = User::count();

    $file = importStaffCsv([
        'Anna Accountant,anna.acct@example.com,accountant,,,,,UBA,Window 1,',
        'Sam Sao,sam.sao@example.com,sao,,,,,,,Admissions',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('preview.summary.total', 2)
        ->where('preview.summary.valid', 2)
        ->where('preview.summary.invalid', 0)
        ->where('preview.fatal', null)
    );

    expect(User::count())->toBe($before);
    Mail::assertNotQueued(UserInvitationMail::class);
});

/*
|--------------------------------------------------------------------------
| 2. Per-row validation matrix
|--------------------------------------------------------------------------
*/

it('flags each invalid row on preview and writes nothing', function () {
    Mail::fake();
    importStaffDepartment('CS');

    // A user that exists then is soft-deleted: email must still collide.
    $trashed = User::factory()->create(['email' => 'gone@example.com']);
    $trashed->delete();

    $before = User::withTrashed()->count();

    // Lines are crafted so each has exactly one problem. The importer numbers
    // rows by physical CSV line (header = line 1), so the 12 data rows below are
    // lines 2..13.
    $file = importStaffCsv([
        'Bad Email,not-an-email,accountant,,,,,UBA,W1,',           // 1: invalid email
        ',missing.name@example.com,accountant,,,,,UBA,W1,',         // 2: missing name
        'Unknown Role,teacher@example.com,teacher,,,,,,,',          // 3: unknown role
        'Not Creatable,studentrole@example.com,student,,,,,,,',     // 4: non-creatable role
        'Bad Dept,lect.baddept@example.com,lecturer,,ZZ,,,,,',      // 5: unresolvable department_code
        'No Dept,lect.nodept@example.com,lecturer,,,,,,,',          // 6: lecturer missing department_code
        'Bad Emp,bad.emp@example.com,accountant,BAD@ID,,,,UBA,W1,', // 7: bad employee_id
        'Dup One,dup.email@example.com,accountant,,,,,UBA,W1,',     // 8: in-file duplicate email (a)
        'Dup Two,dup.email@example.com,sao,,,,,,,Admissions',       // 9: in-file duplicate email (b)
        'Emp A,empa@example.com,accountant,emp001,,,,UBA,W1,',      // 10: in-file duplicate employee_id (a)
        'Emp B,empb@example.com,accountant,emp001,,,,UBA,W1,',      // 11: in-file duplicate employee_id (b)
        'Trashed Email,gone@example.com,sao,,,,,,,Admissions',      // 12: collides with soft-deleted user
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $rows = collect($page->toArray()['props']['preview']['rows']);

        $statusByLine = $rows->keyBy('line')->map(fn ($row) => $row['status']);

        // In-file duplicate detection only flags the *second* occurrence, so the
        // first row of each dup pair (lines 9 and 11) is legitimately OK; every
        // other crafted row must be an error.
        foreach ([2, 3, 4, 5, 6, 7, 8, 10, 12, 13] as $line) {
            expect($statusByLine->get($line))->toBe('error', "row {$line} should be an error");
        }

        foreach ([9, 11] as $line) {
            expect($statusByLine->get($line))->toBe('ok', "row {$line} (first of a dup pair) should be ok");
        }

        // At minimum, every error row carries a non-empty errors array.
        $rows->where('status', 'error')->each(function ($row) {
            expect($row['errors'])->not->toBeEmpty();
        });

        return true;
    });

    // 12 data rows: lines 9 and 11 are the valid first-of-pair rows; the other
    // 10 are errors.
    $response->assertInertia(fn ($page) => $page
        ->where('preview.summary.total', 12)
        ->where('preview.summary.invalid', 10)
        ->where('preview.summary.valid', 2)
    );

    // No writes on preview (the soft-deleted user is the only pre-existing row).
    expect(User::withTrashed()->count())->toBe($before);
    Mail::assertNotQueued(UserInvitationMail::class);
});

/*
|--------------------------------------------------------------------------
| 3. Confirm: imports valid rows, skips invalid
|--------------------------------------------------------------------------
*/

it('imports valid rows, skips invalid ones, queues invites and audits the import', function () {
    Mail::fake();
    $department = importStaffDepartment('CS');

    $file = importStaffCsv([
        // Valid lecturer; employee_id given UPPERCASE to prove lowercasing.
        "Lara Lecturer,lara.lect@example.com,lecturer,STM2024X,{$department->code},AI,,,,",
        // Valid accountant.
        'Andy Accountant,andy.acct@example.com,accountant,,,,,UBA,Window 2,',
        // Invalid: bad email.
        'Broken,not-an-email,accountant,,,,,UBA,W1,',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.import.store'), ['file' => $file]);

    $response->assertOk();

    // Both valid users now exist with the right roles.
    $lecturer = User::query()->where('email', 'lara.lect@example.com')->firstOrFail();
    $accountant = User::query()->where('email', 'andy.acct@example.com')->firstOrFail();

    expect($lecturer->hasRole(RoleName::Lecturer))->toBeTrue();
    expect($accountant->hasRole(RoleName::Accountant))->toBeTrue();

    // The invalid row was not created.
    expect(User::query()->where('email', 'not-an-email')->exists())->toBeFalse();

    // Lecturer profile resolved to the seeded department.
    $profile = LecturerProfile::query()->where('user_id', $lecturer->id)->firstOrFail();
    expect($profile->department_id)->toBe($department->id);

    // employee_id stored lowercased (the regex would also have rejected an
    // uppercase value, so a created lecturer proves canonicalization happened).
    expect($lecturer->fresh()->employee_id)->toBe('stm2024x');

    // Exactly one invitation per imported row.
    Mail::assertQueued(UserInvitationMail::class, 2);

    // Result summary: 3 total, 2 imported, 1 skipped.
    $response->assertInertia(fn ($page) => $page
        ->where('result.summary.total', 3)
        ->where('result.summary.imported', 2)
        ->where('result.summary.skipped', 1)
        ->where('result.fatal', null)
    );

    // A users_imported audit row was recorded.
    expect(AuditLog::query()->where('action', AuditAction::UsersImported)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 4. Fatal header error
|--------------------------------------------------------------------------
*/

it('returns a fatal error and writes nothing when the header is missing role', function () {
    Mail::fake();
    $before = User::count();

    // Header omits the required `role` column.
    $badHeader = 'name,email,employee_id,department_code,specialization,hired_at,bank_desk,cashier_window,scope';

    $file = importStaffCsv([
        'Anna Accountant,anna.acct@example.com,,,,,UBA,Window 1,',
    ], $badHeader);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('preview.fatal', fn ($fatal) => $fatal !== null && $fatal !== '')
        ->where('preview.rows', [])
    );

    expect(User::count())->toBe($before);
    Mail::assertNotQueued(UserInvitationMail::class);
});

/*
|--------------------------------------------------------------------------
| 5. Template download
|--------------------------------------------------------------------------
*/

it('serves a downloadable CSV template with the column header line', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.users.import.template'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->streamedContent())->toContain('name,email,role');
});

/*
|--------------------------------------------------------------------------
| 6. Authorization — non-admin is forbidden everywhere
|--------------------------------------------------------------------------
*/

it('forbids non-admin users from every import endpoint', function () {
    $lecturer = userWithRole(RoleName::Lecturer);
    $file = importStaffCsv([
        'Anna Accountant,anna.acct@example.com,accountant,,,,,UBA,Window 1,',
    ]);

    $this->actingAs($lecturer)->get(route('admin.users.import'))->assertForbidden();
    $this->actingAs($lecturer)->get(route('admin.users.import.template'))->assertForbidden();
    $this->actingAs($lecturer)
        ->post(route('admin.users.import.preview'), ['file' => $file])
        ->assertForbidden();
    $this->actingAs($lecturer)
        ->post(route('admin.users.import.store'), ['file' => importStaffCsv([
            'Anna Accountant,anna.acct@example.com,accountant,,,,,UBA,Window 1,',
        ])])
        ->assertForbidden();
});
