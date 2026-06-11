<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * User provider wired to the password broker only (`passwords.users.provider`
 * in config/auth.php) — the session guard keeps the default provider, so
 * soft-deleted users still cannot authenticate.
 *
 * Includes trashed users so a returning user can reactivate their account
 * through the reset flow: the reset token mailed to the address is the proof
 * of mailbox ownership that the old register-time reactivation lacked
 * (plan/context.md §13, AUDIT.md AUD-004). Trashed staff/admin accounts are
 * filtered out — those are deactivated deliberately and only an admin may
 * restore them via the user-management module.
 */
class PasswordBrokerUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null): Builder
    {
        /** @var Builder<Model&User> $query */
        $query = parent::newModelQuery($model);

        return $query->withTrashed();
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $user = parent::retrieveByCredentials($credentials);

        if ($user instanceof User && $user->trashed() && $user->hasAnyRole(RoleName::staff())) {
            return null;
        }

        return $user;
    }
}
