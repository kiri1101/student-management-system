<?php

/**
 * Local-only demo data for the academic-report screenshots (Chapter 3).
 *
 * Run in booted app context (NOT wired into DatabaseSeeder):
 *     php artisan migrate:fresh --seed
 *     php artisan tinker --execute="require base_path('plan/report/seed_demo.php');"
 *
 * Produces a coherent happy-path dataset:
 *   - applicant@example.com  : fresh applicant, no application -> application form
 *   - several applications in interim/terminal states          -> SAO queue + admin dashboard
 *   - student@example.com    : admitted Bachelors L1 student
 *       * validated 300,000 XAF payment -> valid HMAC receipt + "cleared" standing
 *       * pending  200,000 XAF payment with a real slip image -> accountant review
 *
 * Real bank-slip PNGs are written to the default disk so the inline viewers
 * render them, and the receipt is minted through the genuine ReviewPaymentAction
 * so the public verify endpoint reports it authentic.
 */

use App\Actions\Accountant\ReviewPaymentAction;
use App\Enums\ApplicationStatus;
use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Application;
use App\Models\PaymentSubmission;
use App\Models\ProgramOffering;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('seed_demo.php is local-only.');
}

$year = (string) now()->year;

/** Draw a believable bank deposit slip as a PNG and store it on the default disk. */
$makeSlip = function (string $bankName, int $amount, string $reference, string $matricule) use ($year): array {
    $w = 1000;
    $h = 1360;
    $img = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($img, 255, 255, 255);
    $ink = imagecolorallocate($img, 17, 24, 39);
    $green = imagecolorallocate($img, 6, 95, 70);
    $grey = imagecolorallocate($img, 107, 114, 128);
    $line = imagecolorallocate($img, 209, 213, 219);
    imagefilledrectangle($img, 0, 0, $w, $h, $white);

    $bold = 'C:/Windows/Fonts/arialbd.ttf';
    $reg = 'C:/Windows/Fonts/arial.ttf';
    $hasFont = is_file($bold) && is_file($reg);

    $text = function (int $size, int $x, int $y, string $s, $color, bool $b = false) use ($img, $bold, $reg, $hasFont): void {
        if ($hasFont) {
            imagettftext($img, $size, 0, $x, $y, $color, $b ? $bold : $reg, $s);
        } else {
            imagestring($img, 5, $x, $y - $size, $s, $color);
        }
    };

    // Header band
    imagefilledrectangle($img, 0, 0, $w, 150, $green);
    $text(30, 60, 70, $bankName, $white, true);
    $text(15, 60, 115, 'CASH / TRANSFER DEPOSIT SLIP', $white);

    // Beneficiary / institution
    $text(16, 60, 230, 'Beneficiary:', $grey);
    $text(18, 260, 230, 'University Institute — Student Tuition Account', $ink, true);
    $text(16, 60, 280, 'Account No:', $grey);
    $text(18, 260, 280, '4001 2233 4455 6677', $ink, true);

    // Boxed details table
    $rows = [
        ['Depositor / Matricule', strtoupper($matricule)],
        ['Bank Reference', $reference],
        ['Value Date', now()->format('d M Y')],
        ['Academic Year', $year],
        ['Currency', 'XAF'],
    ];
    $top = 360;
    $rowH = 90;
    foreach ($rows as $i => [$k, $v]) {
        $y = $top + $i * $rowH;
        imagerectangle($img, 60, $y, $w - 60, $y + $rowH, $line);
        imageline($img, 460, $y, 460, $y + $rowH, $line);
        $text(16, 80, $y + 56, $k, $grey);
        $text(20, 480, $y + 56, $v, $ink, true);
    }

    // Amount panel
    $ay = $top + count($rows) * $rowH + 40;
    imagefilledrectangle($img, 60, $ay, $w - 60, $ay + 130, imagecolorallocate($img, 236, 253, 245));
    imagerectangle($img, 60, $ay, $w - 60, $ay + 130, $green);
    $text(18, 90, $ay + 55, 'AMOUNT DEPOSITED', $green, true);
    $text(40, 90, $ay + 110, number_format($amount, 0, '.', ' ').' XAF', $ink, true);

    // Stamp + footer
    $text(15, 60, $h - 120, 'Teller stamp / signature', $grey);
    imagerectangle($img, 600, $h - 230, $w - 60, $h - 90, $line);
    $text(14, 60, $h - 50, 'System-generated demo slip for the SchuLyf report — not a real transaction.', $grey);

    $tmp = tempnam(sys_get_temp_dir(), 'slip').'.png';
    imagepng($img, $tmp);
    imagedestroy($img);

    $storedPath = 'payment-slips/'.\Illuminate\Support\Str::uuid().'.png';
    Storage::put($storedPath, file_get_contents($tmp));
    @unlink($tmp);

    return [$storedPath, 'deposit-slip-'.$reference.'.png', 'image/png'];
};

DB::transaction(function () use ($year, $makeSlip): void {
    $accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $sao = User::where('email', 'sao@example.com')->firstOrFail();

    $offering = ProgramOffering::where('degree_program', 'bachelors')->firstOrFail();

    // ---- 1. Fresh applicant (for the application-form screenshot) -------------
    $applicant = User::firstOrCreate(
        ['email' => 'applicant@example.com'],
        ['name' => 'Aïcha Mballa', 'password' => Hash::make('password')],
    );
    // email_verified_at is not mass-fillable on User; set it explicitly so the
    // `verified` middleware on applicant/student routes lets these demo users in.
    $applicant->markEmailAsVerified();

    // ---- 2. SAO queue + dashboard variety -------------------------------------
    $queue = [
        ['Submitted', 'Brian', 'Tchoua', ApplicationStatus::Submitted, now()->subDays(1)],
        ['Submitted', 'Clarisse', 'Ngo', ApplicationStatus::Submitted, now()->subDays(2)],
        ['UnderReview', 'David', 'Fometeu', ApplicationStatus::UnderReview, now()->subDays(3)],
        ['DocumentsRequested', 'Esther', 'Bih', ApplicationStatus::DocumentsRequested, now()->subDays(4)],
    ];
    foreach ($queue as [, $first, $last, $status, $when]) {
        Application::factory()->submitted()->create([
            'program_offering_id' => $offering->id,
            'level' => 1,
            'first_name' => $first,
            'last_name' => $last,
            'status' => $status->value,
            'submitted_at' => $when,
        ]);
    }
    // Two decided rows so the admin/SAO dashboards show terminal counts too.
    Application::factory()->submitted()->create([
        'program_offering_id' => $offering->id, 'level' => 1,
        'first_name' => 'Franck', 'last_name' => 'Owona',
        'status' => ApplicationStatus::Admitted->value,
        'submitted_at' => now()->subDays(9), 'decided_at' => now()->subDays(5),
        'decided_by_user_id' => $sao->id, 'decision_notes' => 'Strong credentials.',
    ]);
    Application::factory()->submitted()->create([
        'program_offering_id' => $offering->id, 'level' => 1,
        'first_name' => 'Grace', 'last_name' => 'Atanga',
        'status' => ApplicationStatus::Rejected->value,
        'submitted_at' => now()->subDays(10), 'decided_at' => now()->subDays(6),
        'decided_by_user_id' => $sao->id, 'decision_notes' => 'Incomplete documentation.',
    ]);

    // ---- 3. Admitted student --------------------------------------------------
    $studentUser = User::firstOrCreate(
        ['email' => 'student@example.com'],
        ['name' => 'Jordan Nkeng', 'password' => Hash::make('password')],
    );
    $studentUser->markEmailAsVerified();
    $studentUser->assignRole(RoleName::Student);

    $matricule = StudentProfile::nextMatriculeForYear((int) $year);
    $profile = StudentProfile::firstOrCreate(
        ['user_id' => $studentUser->id],
        [
            'matricule' => $matricule,
            'program_offering_id' => $offering->id,
            'level' => 1,
            'academic_year' => $year,
            'enrolled_at' => now()->subMonths(3)->toDateString(),
            'status' => StudentStatus::Active->value,
        ],
    );

    // ---- 4. Validated payment -> valid receipt (cleared standing) -------------
    [$p1, $f1, $m1] = $makeSlip('UBA — United Bank for Africa', 300_000, 'UBA8842130A', $profile->matricule);
    $validated = PaymentSubmission::create([
        'student_profile_id' => $profile->id,
        'academic_year' => $year,
        'bank' => Bank::Uba->value,
        'amount_xaf' => 300_000,
        'bank_reference' => 'UBA8842130A',
        'slip_path' => $p1,
        'slip_original_filename' => $f1,
        'slip_mime_type' => $m1,
        'status' => PaymentStatus::Submitted->value,
    ]);
    app(ReviewPaymentAction::class)->execute($validated, PaymentStatus::Validated, null, $accountant);

    // ---- 5. Pending payment (for the accountant review screenshot) ------------
    [$p2, $f2, $m2] = $makeSlip('Afriland First Bank', 200_000, 'AFG5521907B', $profile->matricule);
    PaymentSubmission::create([
        'student_profile_id' => $profile->id,
        'academic_year' => $year,
        'bank' => Bank::Afg->value,
        'amount_xaf' => 200_000,
        'bank_reference' => 'AFG5521907B',
        'slip_path' => $p2,
        'slip_original_filename' => $f2,
        'slip_mime_type' => $m2,
        'status' => PaymentStatus::Submitted->value,
    ]);
});

$receipt = \App\Models\SchoolReceipt::query()->latest('id')->first();
echo 'Demo data seeded.'.PHP_EOL;
echo 'Receipt: '.($receipt?->receipt_number ?? 'none').' verifies='.($receipt?->verifies() ? 'yes' : 'no').PHP_EOL;
echo 'Applications: '.Application::count().' | Students: '.StudentProfile::count().' | Payments: '.PaymentSubmission::count().PHP_EOL;
