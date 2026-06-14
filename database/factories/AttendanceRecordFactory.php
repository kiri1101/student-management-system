<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\CourseSession;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_session_id' => fn () => CourseSession::factory(),
            'student_profile_id' => fn () => StudentProfile::factory(),
            'status' => fake()->randomElement(AttendanceStatus::cases())->value,
            'marked_by' => fn () => User::factory(),
            'marked_at' => now(),
        ];
    }
}
