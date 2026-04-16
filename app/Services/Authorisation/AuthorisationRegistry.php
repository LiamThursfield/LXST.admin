<?php

namespace App\Services\Authorisation;

use App\Services\Authorisation\Enums\Role;
use BackedEnum;
use Illuminate\Support\Collection;

class AuthorisationRegistry
{
    /** @var Collection<int, BackedEnum> */
    protected Collection $permissions;

    /** @var Collection<string, Collection<int, BackedEnum>> */
    protected Collection $rolePermissions;

    public function __construct()
    {
        $this->permissions = collect();
        $this->rolePermissions = collect();
    }

    /**
     * @param  BackedEnum[]  $permissions
     */
    public function addPermissions(array $permissions): self
    {
        foreach ($permissions as $permission) {
            if (! $this->permissions->contains($permission)) {
                $this->permissions->push($permission);
            }
        }

        return $this;
    }

    /**
     * @param  BackedEnum[]  $permissions
     */
    public function assignPermissionsToRole(Role $role, array $permissions): self
    {
        $roleKey = $role->value;

        if (! $this->rolePermissions->has($roleKey)) {
            $this->rolePermissions->put($roleKey, collect());
        }

        foreach ($permissions as $permission) {
            if (! $this->rolePermissions->get($roleKey)?->contains($permission)) {
                $this->rolePermissions->get($roleKey)?->push($permission);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BackedEnum>
     */
    public function allPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * @return Collection<int, BackedEnum>
     */
    public function permissionsForRole(Role $role): Collection
    {
        return $this->rolePermissions->get($role->value, collect());
    }
}
