<?php

namespace App\Models\Concerns;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(RoleName $role): bool
    {
        return $this->roles()->where('name', $role->value)->exists();
    }

    /**
     * @param  array<int, RoleName>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        $values = array_map(fn (RoleName $role): string => $role->value, $roles);

        return $this->roles()->whereIn('name', $values)->exists();
    }

    public function assignRole(RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->value('id');

        if ($roleId === null) {
            throw new \RuntimeException("Role [{$role->value}] is not seeded.");
        }

        $this->roles()->syncWithoutDetaching([$roleId]);
    }

    public function removeRole(RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->value('id');

        if ($roleId === null) {
            return;
        }

        $this->roles()->detach($roleId);
    }
}
