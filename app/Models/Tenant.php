<?php

namespace App\Models;

use App\Enums\TenantModule;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $name
 * @property Collection<int, TenantModule>|null $modules
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * @return array<string>
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'modules',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules' => AsEnumCollection::of(TenantModule::class),
        ];
    }

    /**
     * Returns true if the tenant has the given module switched on
     */
    public function hasModule(TenantModule $module): bool
    {
        return $this->modules?->contains($module) ?? false;
    }
}
