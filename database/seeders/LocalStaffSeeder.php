<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\AccountantProfile;
use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\SaoProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only convenience: provisions one Lecturer, one Accountant, and one
 * SAO so the SAO admission flow is reachable immediately after a fresh seed.
 * Skipped outside `local`/`testing` environments — production must onboard
 * staff through the admin user-management UI.
 */
class LocalStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $department = Department::query()->orderBy('id')->first();

        $lecturer = $this->seedUser('Sample Lecturer', 'lecturer@example.com');
        $lecturer->assignRole(RoleName::Lecturer);
        if ($department !== null) {
            LecturerProfile::query()->firstOrCreate(
                ['user_id' => $lecturer->id],
                [
                    'department_id' => $department->id,
                    'specialization' => 'Software Engineering',
                    'hired_at' => now()->subYears(2)->toDateString(),
                ],
            );
        }

        $accountant = $this->seedUser('Sample Accountant', 'accountant@example.com');
        $accountant->assignRole(RoleName::Accountant);
        AccountantProfile::query()->firstOrCreate(
            ['user_id' => $accountant->id],
            [
                'bank_desk' => 'Main Campus — Desk A',
                'cashier_window' => 'Window 1',
            ],
        );

        $sao = $this->seedUser('Sample SAO', 'sao@example.com');
        $sao->assignRole(RoleName::Sao);
        SaoProfile::query()->firstOrCreate(
            ['user_id' => $sao->id],
            ['scope' => 'Admissions'],
        );
    }

    private function seedUser(string $name, string $email): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
