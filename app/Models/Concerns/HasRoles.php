<?php

namespace App\Models\Concerns;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Whether the user holds the given role.
     */
    public function hasRole(RoleName $role): bool
    {
        return $this->hasAnyRole([$role]);
    }

    /**
     * Answered from the already-loaded relation when available so the many
     * per-request gate/middleware checks share a single roles query
     * (AUDIT.md AUD-018); falls back to an EXISTS query otherwise.
     *
     * @param  array<int, RoleName>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(
                fn (Role $assigned): bool => in_array($assigned->name, $roles, strict: true),
            );
        }

        $values = array_map(fn (RoleName $role): string => $role->value, $roles);

        return $this->roles()->whereIn('name', $values)->exists();
    }

    /**
     * Attach the role idempotently. Throws if the role has not been seeded.
     */
    public function assignRole(RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->value('id');

        if ($roleId === null) {
            throw new \RuntimeException("Role [{$role->value}] is not seeded.");
        }

        $this->roles()->syncWithoutDetaching([$roleId]);
        $this->unsetRelation('roles');
    }

    /**
     * Detach the role if present; a no-op when the role is unseeded or unattached.
     */
    public function removeRole(RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->value('id');

        if ($roleId === null) {
            return;
        }

        $this->roles()->detach($roleId);
        $this->unsetRelation('roles');
    }
}
