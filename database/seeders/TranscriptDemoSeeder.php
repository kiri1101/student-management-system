<?php

namespace Database\Seeders;

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\Department;
use App\Models\ProgramOffering;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only convenience: provisions one student with published course results
 * across two semesters (a mix of A/B/C plus one F) so a generated transcript and
 * its public verify page have real content to render. Skipped outside
 * `local`/`testing` and idempotent — a second run adds nothing.
 */
class TranscriptDemoSeeder extends Seeder
{
    /**
     * ca/exam marks chosen to land on specific letter grades via
     * `CourseResult::grade` (weighted final = 0.3·ca + 0.7·exam; A≥80/B≥70/C≥60/D≥50/F).
     *
     * @var list<array{code: string, title: string, credits: int, semester: int, ca: int, exam: int}>
     */
    private const COURSES = [
        ['code' => 'CSC201', 'title' => 'Data Structures', 'credits' => 4, 'semester' => 1, 'ca' => 85, 'exam' => 85],
        ['code' => 'CSC202', 'title' => 'Discrete Mathematics', 'credits' => 3, 'semester' => 1, 'ca' => 72, 'exam' => 72],
        ['code' => 'CSC203', 'title' => 'Introduction to Databases', 'credits' => 3, 'semester' => 1, 'ca' => 62, 'exam' => 62],
        ['code' => 'CSC204', 'title' => 'Operating Systems', 'credits' => 4, 'semester' => 2, 'ca' => 88, 'exam' => 90],
        ['code' => 'CSC205', 'title' => 'Computer Networks', 'credits' => 3, 'semester' => 2, 'ca' => 30, 'exam' => 25],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $student = User::query()->firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Sample Student',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $student->assignRole(RoleName::Student);

        $offering = $this->resolveOffering();

        $profile = StudentProfile::query()->firstOrCreate(
            ['user_id' => $student->id],
            [
                'matricule' => 'stm-2024-0001',
                'program_offering_id' => $offering->id,
                'level' => 200,
                'academic_year' => '2024',
                'enrolled_at' => now()->subYear()->toDateString(),
                'status' => StudentStatus::Active->value,
            ],
        );

        // Idempotent: only seed the academic history once.
        if (CourseResult::query()->where('student_profile_id', $profile->id)->exists()) {
            return;
        }

        foreach (self::COURSES as $spec) {
            $course = Course::factory()->approved()->create([
                'program_offering_id' => $offering->id,
                'code' => $spec['code'],
                'title' => $spec['title'],
                'credits' => $spec['credits'],
                'semester' => $spec['semester'],
                'academic_year' => '2024',
            ]);

            CourseResult::factory()->published()->create([
                'course_id' => $course->id,
                'student_profile_id' => $profile->id,
                'ca_score' => $spec['ca'],
                'exam_score' => $spec['exam'],
            ]);
        }
    }

    private function resolveOffering(): ProgramOffering
    {
        $existing = ProgramOffering::query()->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $department = Department::query()->firstOrCreate(
            ['code' => 'CS'],
            ['name' => 'Computer Science'],
        );

        return ProgramOffering::query()->firstOrCreate(
            ['department_id' => $department->id, 'degree_program' => DegreeProgram::Bachelors->value],
            ['min_level' => 100, 'max_level' => 400],
        );
    }
}
