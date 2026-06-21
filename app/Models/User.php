<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * The single authentication identity shared by every actor in the system.
 * Roles are carried via the `role_user` pivot (HasRoles), and role-specific
 * data hangs off per-role profile HasOne relations — at most one profile per
 * role, with staff holding exactly one role (ADR-0002).
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsAudit, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasOne<LecturerProfile, $this>
     */
    public function lecturerProfile(): HasOne
    {
        return $this->hasOne(LecturerProfile::class);
    }

    /**
     * @return HasOne<AccountantProfile, $this>
     */
    public function accountantProfile(): HasOne
    {
        return $this->hasOne(AccountantProfile::class);
    }

    /**
     * @return HasOne<SaoProfile, $this>
     */
    public function saoProfile(): HasOne
    {
        return $this->hasOne(SaoProfile::class);
    }
}
