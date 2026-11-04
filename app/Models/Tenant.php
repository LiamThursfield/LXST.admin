<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Services\Industry\IndustryProfile;
use App\Services\Industry\IndustryProfiles;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $name
 * @property Collection<int, TenantModule>|null $modules
 * @property string $industry The primary industry, a key of config('industries.profiles')
 * @property array{terms?: array<string, array{singular: string, plural: string}>, booking_types?: list<string>, customer_fields?: list<string>}|null $industry_overrides
 * @property Carbon|null $disabled_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Until it's saved and reloaded, a new tenant wouldn't have the column's default
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'industry' => 'other',
    ];

    /**
     * @return array<string>
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'modules',
            'industry',
            'industry_overrides',
            'disabled_at',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules' => AsEnumCollection::of(TenantModule::class),
            'industry_overrides' => 'array',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Returns true if the tenant has the given module switched on
     */
    public function hasModule(TenantModule $module): bool
    {
        return $this->modules?->contains($module) ?? false;
    }

    /**
     * The tenant's industry. Everything that depends on the industry goes through here, so a
     * tenant could have more than one later without changing what uses it.
     */
    public function industryProfile(): IndustryProfile
    {
        return app(IndustryProfiles::class)->find($this->industry);
    }

    /**
     * The terms the tenant has changed, see Terminology for the full list
     *
     * @return array<string, array{singular: string, plural: string}>
     */
    public function termOverrides(): array
    {
        return $this->industry_overrides['terms'] ?? [];
    }

    /**
     * The booking types the tenant uses: its own choice, or its industry's
     *
     * @return list<BookingType>
     */
    public function bookingTypes(): array
    {
        $overrides = $this->industry_overrides['booking_types'] ?? null;

        return $overrides === null
            ? $this->industryProfile()->bookingTypes
            : array_map(fn (string $type) => BookingType::from($type), $overrides);
    }

    /**
     * The customer fields the tenant uses: its own choice, or its industry's
     *
     * @return list<string>
     */
    public function customerFields(): array
    {
        return $this->industry_overrides['customer_fields'] ?? $this->industryProfile()->customerFields;
    }

    /**
     * Whether the tenant has been switched off (see EnsureTenantIsEnabled)
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }
}
