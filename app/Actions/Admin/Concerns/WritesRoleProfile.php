<?php

namespace App\Actions\Admin\Concerns;

use App\Enums\RoleName;
use App\Models\AccountantProfile;
use App\Models\LecturerProfile;
use App\Models\SaoProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait WritesRoleProfile
{
    /**
     * Write the role-specific profile row for $user, or null when the role
     * has no profile (Admin). Restores a trashed profile in place rather
     * than colliding on the unique user_id constraint.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function writeProfile(User $user, RoleName $role, array $payload): ?Model
    {
        $model = match ($role) {
            RoleName::Lecturer => LecturerProfile::class,
            RoleName::Accountant => AccountantProfile::class,
            RoleName::Sao => SaoProfile::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        $existing = $model::withTrashed()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($payload)->save();

            return $existing;
        }

        return $model::create(['user_id' => $user->id] + $payload);
    }

    /**
     * Soft-delete any active staff profile attached to $user. Used when
     * changing roles so the unique user_id constraint stays clean.
     */
    protected function softDeleteStaffProfile(User $user): void
    {
        $user->lecturerProfile()->delete();
        $user->accountantProfile()->delete();
        $user->saoProfile()->delete();
    }
}
